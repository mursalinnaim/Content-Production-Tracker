<?php

namespace App\Services;

use App\Data\ContentPlan;
use App\Data\ContentPlanResult;
use App\Exceptions\ContentGenerationException;
use App\Models\ContentGeneration;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class OpenAIService
{
    private const MAX_PROJECT_TITLE_LENGTH = 200;
    private const MAX_PROJECT_BRIEF_LENGTH = 5000;
    private const MAX_PROJECT_NOTES_LENGTH = 5000;
    private const MAX_REGENERATION_INSTRUCTIONS_LENGTH = 2000;
    private const MAX_PROMPT_LENGTH = 30000;
    private const MAX_OUTPUT_TOKENS = 2048;
    /** @return array{prompt: string, model: ?string, pricing: ?array{inputRate: string, outputRate: string, currency: string, source: string, checkedAt: string}} */
    public function prepareGeneration(Project $project): array
    {
        $model = config('services.openai.model');

        $this->validateProjectContent($project);
        $prompt = $this->buildPrompt($project);
        $this->validatePrompt($prompt);

        return [
            'prompt' => $prompt,
            'model' => $model,
            'pricing' => is_string($model) ? $this->pricingFor($model) : null,
        ];
    }

    /** @return array{prompt: string, model: ?string, pricing: ?array{inputRate: string, outputRate: string, currency: string, source: string, checkedAt: string}} */
    public function prepareRegeneration(
        Project $project,
        ContentGeneration $sourceGeneration,
        string $instructions,
    ): array {
        $draft = $sourceGeneration->getAttribute('draft');
        $response = $sourceGeneration->getAttribute('response');
        $content = is_array($draft)
            ? $draft
            : (is_array($response) ? $response : null);
        $this->validateProjectContent($project);
        $this->validateRegenerationInstructions($instructions);

        if ($content === null) {
            throw new InvalidArgumentException('The source content plan is invalid or unavailable.');
        }

        ContentPlan::fromArray($content);
        $prompt = $this->buildRegenerationPrompt($project, $content, $instructions);
        $this->validatePrompt($prompt);
        $model = config('services.openai.model');

        return [
            'prompt' => $prompt,
            'model' => $model,
            'pricing' => is_string($model) ? $this->pricingFor($model) : null,
        ];
    }

    /** @return array{inputRate: string, outputRate: string, currency: string, source: string, checkedAt: string}|null */
    public function pricingFor(string $model): ?array
    {
        $inputRate = config("generation.cost.input_rate_per_million.{$model}");
        $outputRate = config("generation.cost.output_rate_per_million.{$model}");

        if (! is_string($inputRate) || ! is_string($outputRate)) {
            return null;
        }

        return [
            'inputRate' => $inputRate,
            'outputRate' => $outputRate,
            'currency' => (string) config('generation.cost.currency', 'USD'),
            'source' => (string) config('generation.cost.pricing_source'),
            'checkedAt' => (string) config('generation.cost.pricing_checked_at'),
        ];
    }

    public function generateContentPlan(
        Project $project,
        ?string $prompt = null,
        ?string $model = null,
    ): ContentPlanResult {
        $prompt ??= $this->buildPrompt($project);
        $model = func_num_args() >= 3 ? $model : config('services.openai.model');
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey) || empty($model)) {
            throw new ContentGenerationException(
                'OpenAI is not configured.',
                $prompt,
                $model ?? 'unknown',
                'configuration_error',
            );
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
                    'input' => [
                        ['role' => 'system', 'content' => 'You generate structured content plans using only the provided project information.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'content_plan',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'suggested_title' => ['type' => 'string', 'maxLength' => 200],
                                    'content_brief' => ['type' => 'string', 'maxLength' => 5000],
                                    'outline' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'additionalProperties' => false,
                                            'properties' => [
                                                'heading' => ['type' => 'string'],
                                                'purpose' => ['type' => 'string'],
                                            ],
                                            'required' => ['heading', 'purpose'],
                                        ],
                                    ],
                                    'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'production_tasks' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'risks_or_missing_information' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                                'required' => [
                                    'suggested_title',
                                    'content_brief',
                                    'outline',
                                    'key_points',
                                    'production_tasks',
                                    'risks_or_missing_information',
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->status() === 429) {
                throw new ContentGenerationException('OpenAI rate limit reached.', $prompt, $model, 'provider_rate_limited');
            }

            if ($response->failed()) {
                throw new ContentGenerationException('OpenAI request failed.', $prompt, $model, 'provider_error');
            }

            if ($response->json('status') === 'incomplete' || $response->json('incomplete_details') !== null) {
                throw new ContentGenerationException('OpenAI returned an incomplete response.', $prompt, $model, 'incomplete_response');
            }

            $output = $response->json('output');
            $content = null;

            if (is_array($output)) {
                foreach ($output as $outputItem) {
                    if (! is_array($outputItem) || ! is_array($outputItem['content'] ?? null)) {
                        continue;
                    }

                    foreach ($outputItem['content'] as $contentItem) {
                        if (is_array($contentItem) && is_string($contentItem['text'] ?? null)) {
                            $content = $contentItem['text'];
                            break 2;
                        }
                    }
                }
            }

            if (! is_string($content) || trim($content) === '') {
                throw new ContentGenerationException('OpenAI returned an empty response.', $prompt, $model, 'empty_response');
            }

            try {
                $contentPlan = ContentPlan::fromJson($content);
            } catch (\JsonException|InvalidArgumentException) {
                throw new ContentGenerationException('OpenAI returned invalid or out-of-bounds content.', $prompt, $model, 'invalid_output');
            }

            $inputTokens = $response->json('usage.input_tokens');
            $outputTokens = $response->json('usage.output_tokens');

            return new ContentPlanResult(
                contentPlan: $contentPlan,
                inputTokens: is_int($inputTokens) ? $inputTokens : null,
                outputTokens: is_int($outputTokens) ? $outputTokens : null,
                prompt: $prompt,
                model: $model,
            );
        } catch (ContentGenerationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ContentGenerationException('Content generation failed.', $prompt, $model, 'generation_failed');
        }
    }

    public function validateProjectContent(Project $project): void
    {
        $this->validateLength((string) $project->title, self::MAX_PROJECT_TITLE_LENGTH, 'Project title');
        $this->validateLength((string) $project->brief, self::MAX_PROJECT_BRIEF_LENGTH, 'Project brief');
        $this->validateLength((string) $project->notes, self::MAX_PROJECT_NOTES_LENGTH, 'Project notes');

        if (blank($project->title) || blank($project->content_type) || blank($project->brief)) {
            throw new InvalidArgumentException('Project is missing required information.');
        }
    }

    private function validateRegenerationInstructions(string $instructions): void
    {
        if (trim($instructions) === '') {
            throw new InvalidArgumentException('Regeneration instructions are required.');
        }

        $this->validateLength($instructions, self::MAX_REGENERATION_INSTRUCTIONS_LENGTH, 'Regeneration instructions');
    }

    private function validatePrompt(string $prompt): void
    {
        $this->validateLength($prompt, self::MAX_PROMPT_LENGTH, 'The composed prompt');
    }

    private function validateLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$field} exceeds the maximum length of {$max} characters.");
        }
    }

    /** @param array<string, mixed>|null $content */
    private function buildRegenerationPrompt(Project $project, ?array $content, string $instructions): string
    {
        $plan = json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        return <<<PROMPT
Create a revised content production plan using only the project information and the existing plan below.

Project title:
{$project->title}

Content type:
{$project->content_type}

Brief:
{$project->brief}

Notes:
{$project->notes}

Existing plan:
{$plan}

User regeneration instructions:
{$instructions}

Preserve useful information from the existing plan unless the user's instructions require a change. Do not invent specific facts that are not present in the project information. If important information is missing, mention it in risks_or_missing_information.
PROMPT;
    }

    private function buildPrompt(Project $project): string
    {
        return <<<PROMPT
Create a practical content production plan using only the following project information.

Project title:
{$project->title}

Content type:
{$project->content_type}

Brief:
{$project->brief}

Notes:
{$project->notes}

Do not invent specific facts that are not present in the project information.

If important information is missing, mention it in risks_or_missing_information.
PROMPT;
    }
}

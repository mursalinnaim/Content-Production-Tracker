<?php

namespace App\Services;

use App\Data\ContentPlan;
use App\Data\ContentPlanResult;
use App\Exceptions\ContentGenerationException;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIService
{
    public function generateContentPlan(Project $project): ContentPlanResult
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model');
        $prompt = $this->buildPrompt($project);

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
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'You generate structured content plans using only the provided project information.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
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
                                    'suggested_title' => ['type' => 'string'],
                                    'content_brief' => ['type' => 'string'],
                                    'outline' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'additionalProperties' => false,
                                            'properties' => [
                                                'heading' => ['type' => 'string'],
                                                'purpose' => ['type' => 'string'],
                                            ],
                                            'required' => [
                                                'heading',
                                                'purpose',
                                            ],
                                        ],
                                    ],
                                    'key_points' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'production_tasks' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'risks_or_missing_information' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
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

            if ($response->failed()) {
                throw new ContentGenerationException(
                    'OpenAI request failed.',
                    $prompt,
                    $model,
                    'provider_error',
                );
            }

            $content = $response->json('output.0.content.0.text');

            if (! is_string($content) || trim($content) === '') {
                throw new ContentGenerationException(
                    'OpenAI returned an empty response.',
                    $prompt,
                    $model,
                    'empty_response',
                );
            }

            $result = json_decode($content, true);

            if (! is_array($result)) {
                throw new ContentGenerationException(
                    'OpenAI returned invalid JSON.',
                    $prompt,
                    $model,
                    'invalid_json',
                );
            }

            $inputTokens = $response->json('usage.input_tokens');
            $outputTokens = $response->json('usage.output_tokens');

            return new ContentPlanResult(
                contentPlan: ContentPlan::fromArray($result),
                inputTokens: is_int($inputTokens) ? $inputTokens : null,
                outputTokens: is_int($outputTokens) ? $outputTokens : null,
                prompt: $prompt,
                model: $model,
            );
        } catch (ContentGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ContentGenerationException(
                'Content generation failed.',
                $prompt,
                $model,
                'generation_failed',
            );
        }
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

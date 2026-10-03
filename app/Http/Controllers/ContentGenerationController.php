<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateContentPlan;
use App\Models\AcceptedContentPlan;
use App\Models\ContentGeneration;
use App\Models\Project;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use InvalidArgumentException;
use Throwable;

class ContentGenerationController extends Controller
{
    private function generationRateLimitKey(Request $request): string
    {
        return 'generation-submissions:user:'.$request->user()->id;
    }

    private function ensureSubmissionAllowed(Request $request): ?JsonResponse
    {
        $key = $this->generationRateLimitKey($request);
        $maxAttempts = (int) config('generation.rate_limit.max_attempts', 5);
        $decaySeconds = (int) config('generation.rate_limit.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'code' => 'generation_rate_limited',
                'message' => 'Too many generation requests. Please wait before trying again.',
                'retry_after' => $retryAfter,
            ], 429, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);

        return null;
    }

    public function store(
        Request $request,
        Project $project,
        OpenAIService $openAIService,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);

        if ($rateLimitResponse = $this->ensureSubmissionAllowed($request)) {
            return $rateLimitResponse;
        }

        try {
            $prepared = $openAIService->prepareGeneration($project);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $generation = DB::transaction(function () use ($project, $prepared): ContentGeneration {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeGeneration = $lockedProject->contentGenerations()
                ->whereIn('status', ['pending', 'processing'])
                ->latest('id')
                ->first();

            if ($activeGeneration !== null) {
                return $activeGeneration;
            }

            $generationNumber = (int) $lockedProject->contentGenerations()
                ->max('generation_number') + 1;

            return $lockedProject->contentGenerations()->create([
                'generation_number' => $generationNumber,
                'status' => 'pending',
                'prompt' => $prepared['prompt'],
                'model' => $prepared['model'],
                'input_cost_per_million' => $prepared['pricing']['inputRate'] ?? null,
                'output_cost_per_million' => $prepared['pricing']['outputRate'] ?? null,
                'cost_currency' => $prepared['pricing']['currency'] ?? null,
                'pricing_source' => $prepared['pricing']['source'] ?? null,
                'pricing_checked_at' => $prepared['pricing']['checkedAt'] ?? null,
            ]);
        });

        if ($generation->wasRecentlyCreated) {
            try {
                GenerateContentPlan::dispatch($generation->id)->afterCommit();
            } catch (Throwable $exception) {
                report($exception);

                $generation->update([
                    'status' => 'failed',
                    'error_code' => 'queue_dispatch_failed',
                    'error_message' => 'The content plan could not be queued safely. Please try again later.',
                    'completed_at' => now(),
                ]);
            }
        }

        return response()->json([
            'generation' => $this->generationPayload($generation->fresh()),
        ], 202);
    }

    public function show(
        Request $request,
        Project $project,
        ContentGeneration $generation,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);
        abort_unless($generation->project_id === $project->id, 404);

        return response()->json([
            'generation' => $this->generationPayload($generation),
        ]);
    }

    public function saveDraft(
        Request $request,
        Project $project,
        ContentGeneration $generation,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);
        abort_unless($generation->project_id === $project->id, 404);

        if ($generation->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed content plans can be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'draft' => ['required', 'array:suggested_title,content_brief,outline,key_points,production_tasks,risks_or_missing_information'],
            'draft.suggested_title' => ['required', 'string', 'max:200'],
            'draft.content_brief' => ['required', 'string', 'max:5000'],
            'draft.outline' => ['required', 'array', 'list', 'max:20'],
            'draft.outline.*' => ['required', 'array:heading,purpose'],
            'draft.outline.*.heading' => ['required', 'string', 'max:200'],
            'draft.outline.*.purpose' => ['required', 'string', 'max:1000'],
            'draft.key_points' => ['required', 'array', 'list', 'max:20'],
            'draft.key_points.*' => ['required', 'string', 'max:1000'],
            'draft.production_tasks' => ['required', 'array', 'list', 'max:20'],
            'draft.production_tasks.*' => ['required', 'string', 'max:1000'],
            'draft.risks_or_missing_information' => ['required', 'array', 'list', 'max:20'],
            'draft.risks_or_missing_information.*' => ['required', 'string', 'max:1000'],
        ]);

        $generation->update([
            'draft' => $validated['draft'],
        ]);

        return response()->json([
            'generation' => $this->generationPayload($generation->fresh()),
        ]);
    }

    public function accepted(
        Request $request,
        Project $project,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);

        $accepted = $project->acceptedContentPlan;

        return response()->json([
            'accepted_content_plan' => $accepted === null
                ? null
                : $this->acceptedPayload($accepted),
        ]);
    }

    public function accept(
        Request $request,
        Project $project,
        ContentGeneration $generation,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);
        abort_unless($generation->project_id === $project->id, 404);

        if (
            $generation->status !== 'completed' ||
            ($generation->response === null && $generation->draft === null)
        ) {
            return response()->json([
                'message' => 'Only completed content plans can be accepted.',
            ], 422);
        }

        $accepted = DB::transaction(function () use ($project, $generation): AcceptedContentPlan {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $content = $generation->draft ?? $generation->response;
            $existing = $lockedProject->acceptedContentPlan;

            if (
                $existing !== null &&
                $existing->source_generation_id === $generation->id &&
                $existing->content === $content
            ) {
                return $existing;
            }

            return $lockedProject->acceptedContentPlan()->updateOrCreate(
                [],
                [
                    'source_generation_id' => $generation->id,
                    'content' => $content,
                    'accepted_at' => now(),
                ],
            );
        });

        return response()->json([
            'accepted_content_plan' => $this->acceptedPayload($accepted),
        ]);
    }

    public function regenerate(
        Request $request,
        Project $project,
        ContentGeneration $generation,
        OpenAIService $openAIService,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);
        abort_unless($generation->project_id === $project->id, 404);

        if ($rateLimitResponse = $this->ensureSubmissionAllowed($request)) {
            return $rateLimitResponse;
        }

        $validated = $request->validate([
            'instructions' => ['required', 'string', 'filled', 'max:2000'],
        ]);

        try {
            $prepared = $openAIService->prepareRegeneration(
                $project,
                $generation,
                trim($validated['instructions']),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        try {
            $prepared = $openAIService->prepareRegeneration(
                $project,
                $generation,
                trim($validated['instructions']),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $newGeneration = DB::transaction(function () use (
            $project,
            $generation,
            $validated,
            $openAIService,
        ): ContentGeneration {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $generation->status !== 'completed' ||
                ($generation->response === null && $generation->draft === null)
            ) {
                abort(422, 'Only completed content plans can be regenerated.');
            }

            $activeGeneration = $lockedProject->contentGenerations()
                ->whereIn('status', ['pending', 'processing'])
                ->latest('id')
                ->first();

            if ($activeGeneration !== null) {
                return $activeGeneration;
            }

            $sourceGeneration = $lockedProject->contentGenerations()
                ->whereKey($generation->id)
                ->where('status', 'completed')
                ->firstOrFail();

            $instructions = trim($validated['instructions']);
            if ($instructions === '') {
                abort(422, 'Regeneration instructions are required.');
            }

            $generationNumber = (int) $lockedProject->contentGenerations()
                ->max('generation_number') + 1;

            return $lockedProject->contentGenerations()->create([
                'generation_number' => $generationNumber,
                'source_generation_id' => $sourceGeneration->id,
                'status' => 'pending',
                'prompt' => $prepared['prompt'],
                'regeneration_instructions' => $instructions,
                'model' => $prepared['model'],
                'input_cost_per_million' => $prepared['pricing']['inputRate'] ?? null,
                'output_cost_per_million' => $prepared['pricing']['outputRate'] ?? null,
                'cost_currency' => $prepared['pricing']['currency'] ?? null,
                'pricing_source' => $prepared['pricing']['source'] ?? null,
                'pricing_checked_at' => $prepared['pricing']['checkedAt'] ?? null,
            ]);
        });

        if ($newGeneration->wasRecentlyCreated) {
            try {
                GenerateContentPlan::dispatch($newGeneration->id)->afterCommit();
            } catch (Throwable $exception) {
                report($exception);

                $newGeneration->update([
                    'status' => 'failed',
                    'error_code' => 'queue_dispatch_failed',
                    'error_message' => 'The content plan could not be queued safely. Please try again later.',
                    'completed_at' => now(),
                ]);
            }
        }

        $regenerationQueued = $newGeneration->wasRecentlyCreated;

        return response()->json([
            'generation' => $this->generationPayload($newGeneration->fresh()),
            'regeneration_queued' => $regenerationQueued,
            'message' => $regenerationQueued
                ? 'Regeneration queued.'
                : 'A generation is already in progress. Your instructions were not queued.',
        ], 202);
    }

    public function history(
        Request $request,
        Project $project,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);

        $acceptedGenerationId = $project->acceptedContentPlan?->source_generation_id;

        $generations = $project->contentGenerations()
            ->latest('id')
            ->get()
            ->map(fn (ContentGeneration $generation): array => [
                'id' => $generation->id,
                'generation_number' => $generation->generation_number,
                'status' => $generation->status,
                'source_generation_id' => $generation->source_generation_id,
                'has_draft' => $generation->draft !== null,
                'is_accepted' => $generation->id === $acceptedGenerationId,
                'regeneration_instructions' => $generation->regeneration_instructions,
                'processing_started_at' => $generation->processing_started_at,
                'completed_at' => $generation->completed_at,
            ]);

        return response()->json([
            'generations' => $generations,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptedPayload(AcceptedContentPlan $accepted): array
    {
        return [
            'id' => $accepted->id,
            'source_generation_id' => $accepted->source_generation_id,
            'content' => $accepted->content,
            'accepted_at' => $accepted->accepted_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generationPayload(ContentGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'generation_number' => $generation->generation_number,
            'status' => $generation->status,
            'response' => $generation->response,
            'model' => $generation->model,
            'input_tokens' => $generation->input_tokens,
            'output_tokens' => $generation->output_tokens,
            'input_cost_per_million' => $generation->input_cost_per_million,
            'output_cost_per_million' => $generation->output_cost_per_million,
            'cost_currency' => $generation->cost_currency,
            'estimated_cost' => $generation->estimated_cost,
            'pricing_source' => $generation->pricing_source,
            'pricing_checked_at' => $generation->pricing_checked_at,
            'error_code' => $generation->error_code,
            'error_message' => $generation->error_message,
            'processing_started_at' => $generation->processing_started_at,
            'completed_at' => $generation->completed_at,
            'draft' => $generation->draft,
            'source_generation_id' => $generation->source_generation_id,
            'regeneration_instructions' => $generation->regeneration_instructions,
        ];
    }
}

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
use Throwable;

class ContentGenerationController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        OpenAIService $openAIService,
    ): JsonResponse {
        abort_unless($project->user_id === $request->user()->id, 404);

        if (
            blank($project->title) ||
            blank($project->content_type) ||
            blank($project->brief)
        ) {
            return response()->json([
                'message' => 'Project is missing required information.',
            ], 422);
        }

        $generation = DB::transaction(function () use ($project, $openAIService): ContentGeneration {
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

            $prepared = $openAIService->prepareGeneration($lockedProject);
            $generationNumber = (int) $lockedProject->contentGenerations()
                ->max('generation_number') + 1;

            return $lockedProject->contentGenerations()->create([
                'generation_number' => $generationNumber,
                'status' => 'pending',
                'prompt' => $prepared['prompt'],
                'model' => $prepared['model'],
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
            'draft.suggested_title' => ['required', 'string'],
            'draft.content_brief' => ['required', 'string'],
            'draft.outline' => ['required', 'array'],
            'draft.outline.*' => ['required', 'array:heading,purpose'],
            'draft.outline.*.heading' => ['required', 'string'],
            'draft.outline.*.purpose' => ['required', 'string'],
            'draft.key_points' => ['required', 'array'],
            'draft.key_points.*' => ['required', 'string'],
            'draft.production_tasks' => ['required', 'array'],
            'draft.production_tasks.*' => ['required', 'string'],
            'draft.risks_or_missing_information' => ['required', 'array'],
            'draft.risks_or_missing_information.*' => ['required', 'string'],
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

        $validated = $request->validate([
            'instructions' => ['required', 'string', 'filled'],
        ]);

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

            $prepared = $openAIService->prepareRegeneration(
                $lockedProject,
                $sourceGeneration,
                $instructions,
            );
            $generationNumber = (int) $lockedProject->contentGenerations()
                ->max('generation_number') + 1;

            return $lockedProject->contentGenerations()->create([
                'generation_number' => $generationNumber,
                'source_generation_id' => $sourceGeneration->id,
                'status' => 'pending',
                'prompt' => $prepared['prompt'],
                'regeneration_instructions' => $instructions,
                'model' => $prepared['model'],
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

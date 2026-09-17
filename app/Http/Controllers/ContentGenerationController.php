<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateContentPlan;
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

            return $lockedProject->contentGenerations()->create([
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

    /**
     * @return array<string, mixed>
     */
    private function generationPayload(ContentGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'status' => $generation->status,
            'response' => $generation->response,
            'model' => $generation->model,
            'input_tokens' => $generation->input_tokens,
            'output_tokens' => $generation->output_tokens,
            'error_code' => $generation->error_code,
            'error_message' => $generation->error_message,
            'processing_started_at' => $generation->processing_started_at,
            'completed_at' => $generation->completed_at,
        ];
    }
}

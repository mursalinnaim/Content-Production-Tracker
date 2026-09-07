<?php

namespace App\Http\Controllers;

use App\Exceptions\ContentGenerationException;
use App\Models\ContentGeneration;
use App\Models\Project;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ContentGenerationController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        OpenAIService $openAIService,
    ): JsonResponse {
        abort_unless(
            $project->user_id === $request->user()->id,
            404
        );

        if (
            blank($project->title) ||
            blank($project->content_type) ||
            blank($project->brief)
        ) {
            return response()->json([
                'message' => 'Project is missing required information.',
            ], 422);
        }

        try {
            $result = $openAIService->generateContentPlan($project);

            $generation = ContentGeneration::create([
                'project_id' => $project->id,
                'status' => 'completed',
                'prompt' => $result->prompt,
                'content' => $result->contentPlan->toArray(),
                'model' => $result->model,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);

            return response()->json([
                'generation' => $generation,
            ], 201);
        } catch (ContentGenerationException $exception) {
            ContentGeneration::create([
                'project_id' => $project->id,
                'status' => 'failed',
                'prompt' => $exception->prompt,
                'model' => $exception->model,
                'error_code' => $exception->errorCode,
            ]);

            report($exception);

            return response()->json([
                'message' => 'Content generation failed.',
            ], 502);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Content generation failed.',
            ], 502);
        }
    }
}

<?php

namespace App\Jobs;

use App\Exceptions\ContentGenerationException;
use App\Models\ContentGeneration;
use App\Services\OpenAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class GenerateContentPlan implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 75;

    public function __construct(
        public int $generationId,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("content-generation:{$this->generationId}"))
                ->expireAfter(90)
                ->dontRelease(),
        ];
    }

    public function handle(OpenAIService $openAIService): void
    {
        $generation = ContentGeneration::find($this->generationId);

        if ($generation === null || in_array($generation->status, ['completed', 'failed'], true)) {
            return;
        }

        if ($generation->status === 'processing') {
            $this->markFailedIfStillActive(
                'worker_recovery_required',
                'The generation could not be completed safely by the worker.',
            );

            return;
        }

        $claimed = ContentGeneration::query()
            ->whereKey($this->generationId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

        if ($claimed !== 1) {
            return;
        }

        $generation->refresh();

        try {
            $result = $openAIService->generateContentPlan(
                $generation->project,
                $generation->prompt,
                $generation->model,
            );

            $generation->update([
                'status' => 'completed',
                'response' => $result->contentPlan->toArray(),
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'completed_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);
        } catch (ContentGenerationException $exception) {
            if (
                $exception->errorCode === 'provider_rate_limited'
                && $this->attempts() === 1
            ) {
                $generation->update([
                    'status' => 'pending',
                    'processing_started_at' => null,
                ]);

                $this->release(10);

                return;
            }

            $this->markFailedIfStillActive(
                $exception->errorCode,
                'The content plan could not be generated. Please try again later.',
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->markFailedIfStillActive(
                'generation_failed',
                'The content plan could not be generated. Please try again later.',
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }

        $this->markFailedIfStillActive(
            'worker_failed',
            'The content plan could not be generated. Please try again later.',
        );
    }

    private function markFailedIfStillActive(string $errorCode, string $message): void
    {
        ContentGeneration::query()
            ->whereKey($this->generationId)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => $message,
                'completed_at' => now(),
            ]);
    }
}

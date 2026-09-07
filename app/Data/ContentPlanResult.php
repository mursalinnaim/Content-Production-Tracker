<?php

namespace App\Data;

final readonly class ContentPlanResult
{
    public function __construct(
        public ContentPlan $contentPlan,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public string $prompt,
        public string $model,
    ) {}
}

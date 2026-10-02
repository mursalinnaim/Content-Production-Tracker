<?php

use App\Jobs\GenerateContentPlan;
use App\Models\AcceptedContentPlan;
use App\Models\ContentGeneration;
use App\Models\Project;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.model' => 'gpt-4o-mini',
    ]);
});

function jobReviewPlan(): array
{
    return [
        'suggested_title' => 'Original title',
        'content_brief' => 'Original brief',
        'outline' => [
            ['heading' => 'Introduction', 'purpose' => 'Introduce the topic.'],
        ],
        'key_points' => ['Original point'],
        'production_tasks' => ['Original task'],
        'risks_or_missing_information' => ['Original risk'],
    ];
}

it('preserves the existing response and draft when regeneration fails', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Existing regeneration prompt',
        'model' => 'gpt-4o-mini',
        'response' => jobReviewPlan(),
        'draft' => [
            ...jobReviewPlan(),
            'suggested_title' => 'Edited draft',
        ],
    ]);

    $accepted = AcceptedContentPlan::create([
        'project_id' => $project->id,
        'source_generation_id' => $generation->id,
        'content' => $generation->draft,
        'accepted_at' => now(),
    ]);

    Http::fake([
        'https://api.openai.com/*' => Http::response([], 500),
    ]);

    (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

    $generation->refresh();
    $accepted->refresh();

    expect($generation->status)->toBe('failed')
        ->and($generation->error_code)->toBe('provider_error')
        ->and($generation->response)->toBe(jobReviewPlan())
        ->and($generation->draft['suggested_title'])->toBe('Edited draft')
        ->and($accepted->source_generation_id)->toBe($generation->id)
        ->and($accepted->content['suggested_title'])->toBe('Edited draft');
});

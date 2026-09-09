<?php

use App\Jobs\GenerateContentPlan;
use App\Models\ContentGeneration;
use App\Models\Project;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.openai.api_key' => 'test-openai-key']);
    config(['services.openai.model' => 'gpt-4o-mini']);
});

function fakeSuccessfulOpenAIResponse(): void
{
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'suggested_title' => 'A Better Newsletter',
                        'content_brief' => 'A practical newsletter about the project.',
                        'outline' => [
                            [
                                'heading' => 'Introduction',
                                'purpose' => 'Introduce the topic.',
                            ],
                        ],
                        'key_points' => ['Point one'],
                        'production_tasks' => ['Write the introduction'],
                        'risks_or_missing_information' => ['Audience is not specified'],
                    ]),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ], 200),
    ]);
}

it('creates a pending generation and queues work without calling OpenAI', function () {
    Queue::fake();
    Http::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Newsletter',
        'brief' => 'A newsletter about testing.',
        'notes' => 'Some notes.',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response
        ->assertStatus(202)
        ->assertJsonPath('generation.status', 'pending')
        ->assertJsonPath('generation.model', 'gpt-4o-mini');

    expect(ContentGeneration::count())->toBe(1)
        ->and(ContentGeneration::first()->prompt)->toContain('Test Project');

    Queue::assertPushed(GenerateContentPlan::class, function (GenerateContentPlan $job) {
        return $job->generationId === ContentGeneration::first()->id;
    });

    Http::assertNothingSent();
});

it('executes the same generation in the worker and stores the validated result', function () {
    fakeSuccessfulOpenAIResponse();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Saved prompt',
        'model' => 'gpt-4o-mini',
    ]);

    (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

    $generation->refresh();

    expect($generation->status)->toBe('completed')
        ->and($generation->response['suggested_title'])->toBe('A Better Newsletter')
        ->and($generation->input_tokens)->toBe(100)
        ->and($generation->output_tokens)->toBe(50)
        ->and($generation->completed_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request['model'] === 'gpt-4o-mini'
            && $request['input'][1]['content'] === 'Saved prompt';
    });
});

it('rejects object-shaped collection fields and persists a safe failure', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $basePlan = [
        'suggested_title' => 'A Better Newsletter',
        'content_brief' => 'A practical newsletter about the project.',
        'outline' => [
            [
                'heading' => 'Introduction',
                'purpose' => 'Introduce the topic.',
            ],
        ],
        'key_points' => ['Point one'],
        'production_tasks' => ['Write the introduction'],
        'risks_or_missing_information' => ['Audience is not specified'],
    ];

    foreach (['outline', 'key_points', 'production_tasks', 'risks_or_missing_information'] as $field) {
        foreach ([['unexpected' => 'value'], (object) []] as $invalidValue) {
            Http::fake([
                'https://api.openai.com/v1/responses' => Http::response([
                    'output' => [[
                        'content' => [[
                            'text' => json_encode([
                                ...$basePlan,
                                $field => $invalidValue,
                            ]),
                        ]],
                    ]],
                ], 200),
            ]);

            $generation = ContentGeneration::factory()->create([
                'project_id' => $project->id,
                'status' => 'pending',
                'prompt' => 'Saved prompt',
                'model' => 'gpt-4o-mini',
            ]);

            (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

            $generation->refresh();

            expect($generation->status)->toBe('failed')
                ->and($generation->error_code)->toBe('generation_failed')
                ->and($generation->error_message)->not->toContain('unexpected');
        }
    }
});

it('accepts a reasoning-first response and saves the completed plan', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_123',
                    'content' => [],
                ],
                [
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'suggested_title' => 'Reasoning First',
                            'content_brief' => 'A valid plan after reasoning.',
                            'outline' => [
                                [
                                    'heading' => 'Introduction',
                                    'purpose' => 'Introduce the topic.',
                                ],
                            ],
                            'key_points' => ['Point one'],
                            'production_tasks' => ['Write the introduction'],
                            'risks_or_missing_information' => [],
                        ]),
                    ]],
                ],
            ],
            'usage' => [
                'input_tokens' => 120,
                'output_tokens' => 60,
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Saved prompt',
        'model' => 'gpt-4o-mini',
    ]);

    (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

    $generation->refresh();

    expect($generation->status)->toBe('completed')
        ->and($generation->response['suggested_title'])->toBe('Reasoning First')
        ->and($generation->input_tokens)->toBe(120)
        ->and($generation->output_tokens)->toBe(60)
        ->and($generation->completed_at)->not->toBeNull();
});

it('returns the existing active generation on repeated requests', function () {
    Queue::fake();
    Http::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $first = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertStatus(202);

    $second = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertStatus(202);

    expect($first->json('generation.id'))->toBe($second->json('generation.id'))
        ->and(ContentGeneration::count())->toBe(1);

    Queue::assertPushed(GenerateContentPlan::class, 1);
    Http::assertNothingSent();
});

it('rejects another users project before creating or queuing work', function () {
    Queue::fake();
    Http::fake();

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertNotFound();

    expect(ContentGeneration::count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rejects incomplete project input without creating work', function () {
    Queue::fake();
    Http::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => '',
        'brief' => 'A brief.',
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Project is missing required information.']);

    expect(ContentGeneration::count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('reads generation status only for the owning project', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Saved prompt',
        'model' => 'gpt-4o-mini',
    ]);
    $otherGeneration = ContentGeneration::factory()->create([
        'project_id' => $otherProject->id,
        'status' => 'completed',
        'prompt' => 'Other prompt',
        'model' => 'gpt-4o-mini',
    ]);

    $this
        ->actingAs($user)
        ->getJson(route('projects.generations.show', [$project, $generation]))
        ->assertOk()
        ->assertJsonPath('generation.id', $generation->id)
        ->assertJsonPath('generation.status', 'pending');

    $this
        ->actingAs($user)
        ->getJson(route('projects.generations.show', [$project, $otherGeneration]))
        ->assertNotFound();

    $this
        ->actingAs($otherUser)
        ->getJson(route('projects.generations.show', [$otherProject, $generation]))
        ->assertNotFound();
});

it('marks provider failures as terminal without exposing provider details', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'error' => ['message' => 'SECRET PROVIDER ERROR'],
        ], 500),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Saved prompt',
        'model' => 'gpt-4o-mini',
    ]);

    (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

    $generation->refresh();

    expect($generation->status)->toBe('failed')
        ->and($generation->error_code)->toBe('provider_error')
        ->and($generation->error_message)->not->toContain('SECRET PROVIDER ERROR');
});

it('does not call OpenAI for a terminal generation', function () {
    Http::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'prompt' => 'Saved prompt',
        'model' => 'gpt-4o-mini',
        'response' => [
            'suggested_title' => 'Already complete',
            'content_brief' => 'Already saved',
            'outline' => [],
            'key_points' => [],
            'production_tasks' => [],
            'risks_or_missing_information' => [],
        ],
    ]);

    (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

    Http::assertNothingSent();
    expect($generation->fresh()->status)->toBe('completed');
});

it('uses the saved model instead of current configuration', function () {
    fakeSuccessfulOpenAIResponse();
    config(['services.openai.model' => 'changed-after-queueing']);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Saved prompt',
        'model' => 'gpt-4o-mini',
    ]);

    (new GenerateContentPlan($generation->id))->handle(app(OpenAIService::class));

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o-mini');
});

it('retries a rate limited generation once', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['error' => 'rate limited'], 429)
            ->push([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'hooks' => ['Hook 1'],
                                    'content_ideas' => ['Idea 1'],
                                    'visuals' => ['Visual 1'],
                                    'captions' => ['Caption 1'],
                                ]),
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 20,
                ],
            ], 200),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Social Media',
        'brief' => 'Create a short content plan.',
    ]);

    $generation = ContentGeneration::create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Create a short content plan.',
        'model' => config('services.openai.model'),
    ]);

    $job = new GenerateContentPlan($generation->id);

    try {
        $job->handle(app(OpenAIService::class));
    } catch (Throwable $exception) {
        // The first attempt is expected to release itself for retry.
    }

    expect($generation->fresh()->status)->toBe('pending');
    expect(Http::recorded())->toHaveCount(1);
});

it('marks a generation as failed when the provider times out', function () {
    Http::fake([
        '*' => Http::failedConnection(),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Social Media',
        'brief' => 'Create a short content plan.',
    ]);

    $generation = ContentGeneration::create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Create a short content plan.',
        'model' => config('services.openai.model'),
    ]);

    (new GenerateContentPlan($generation->id))
        ->handle(app(OpenAIService::class));

    $generation->refresh();

    expect($generation->status)->toBe('failed');
    expect($generation->error_message)->not->toBeNull();
});

it('fails safely when OpenAI configuration is missing', function () {
    config([
        'services.openai.api_key' => null,
    ]);

    Http::fake();

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Social Media',
        'brief' => 'Create a short content plan.',
    ]);

    $generation = ContentGeneration::create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Create a short content plan.',
        'model' => 'gpt-4o-mini',
    ]);

    (new GenerateContentPlan($generation->id))
        ->handle(app(OpenAIService::class));

    $generation->refresh();

    expect($generation->status)->toBe('failed');
    expect($generation->error_message)->not->toBeNull();

    Http::assertNothingSent();
});

it('does not retry a non retryable provider failure', function () {
    Http::fake([
        '*' => Http::response([
            'error' => 'server error',
        ], 500),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Social Media',
        'brief' => 'Create a short content plan.',
    ]);

    $generation = ContentGeneration::create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Create a short content plan.',
        'model' => 'gpt-4o-mini',
    ]);

    (new GenerateContentPlan($generation->id))
        ->handle(app(OpenAIService::class));

    $generation->refresh();

    expect($generation->status)->toBe('failed');

    Http::assertSentCount(1);
});

it('does nothing when the generation has been deleted', function () {
    Http::fake();

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Social Media',
        'brief' => 'Create a short content plan.',
    ]);

    $generation = ContentGeneration::create([
        'project_id' => $project->id,
        'status' => 'pending',
        'prompt' => 'Create a short content plan.',
        'model' => 'gpt-4o-mini',
    ]);

    $generationId = $generation->id;

    $generation->delete();

    (new GenerateContentPlan($generationId))
        ->handle(app(OpenAIService::class));

    Http::assertNothingSent();
});

it('does not call the provider for a completed generation', function () {
    Http::fake();

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'content_type' => 'Social Media',
        'brief' => 'Create a short content plan.',
    ]);

    $generation = ContentGeneration::create([
        'project_id' => $project->id,
        'status' => 'completed',
        'prompt' => 'Create a short content plan.',
        'model' => 'gpt-4o-mini',
        'result' => [
            'hooks' => ['Existing hook'],
        ],
    ]);

    (new GenerateContentPlan($generation->id))
        ->handle(app(OpenAIService::class));

    expect($generation->fresh()->status)->toBe('completed');

    Http::assertNothingSent();
});

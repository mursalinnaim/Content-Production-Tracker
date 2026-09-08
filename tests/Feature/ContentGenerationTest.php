<?php

use App\Models\ContentGeneration;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.openai.api_key' => 'test-openai-key']);
    config(['services.openai.model' => 'gpt-4o-mini']);
});

it('allows a user to generate a content plan for their own project', function () {
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
                        'risks_or_missing_information' => [
                            'Audience is not specified',
                        ],
                    ]),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ], 200),
    ]);

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
        ->assertCreated()
        ->assertJsonPath('generation.status', 'completed')
        ->assertJsonPath(
            'generation.response.suggested_title',
            'A Better Newsletter'
        );

    expect(ContentGeneration::count())->toBe(1);

    $generation = ContentGeneration::first();

    expect($generation->project_id)->toBe($project->id)
        ->and($generation->model)->toBe('gpt-4o-mini')
        ->and($generation->input_tokens)->toBe(100)
        ->and($generation->output_tokens)->toBe(50);

    Http::assertSentCount(1);
});

it('does not allow a user to generate a plan for another users project', function () {
    Http::fake();

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response->assertNotFound();

    expect(ContentGeneration::count())->toBe(0);

    Http::assertNothingSent();
});

it('rejects a project with missing required information', function () {
    Http::fake();

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => '',
        'content_type' => 'Newsletter',
        'brief' => 'A brief.',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Project is missing required information.',
        ]);

    expect(ContentGeneration::count())->toBe(0);

    Http::assertNothingSent();
});

it('uses the configured model', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'suggested_title' => 'Title',
                        'content_brief' => 'Brief',
                        'outline' => [],
                        'key_points' => [],
                        'production_tasks' => [],
                        'risks_or_missing_information' => [],
                    ]),
                ]],
            ]],
        ], 200),
    ]);

    config(['services.openai.model' => 'test-model']);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertCreated();

    $generation = ContentGeneration::first();

    expect($generation->model)->toBe('test-model');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'test-model';
    });
});

it('stores the generated content and prompt', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'suggested_title' => 'Generated Title',
                        'content_brief' => 'Generated Brief',
                        'outline' => [
                            [
                                'heading' => 'Section',
                                'purpose' => 'Explain something.',
                            ],
                        ],
                        'key_points' => ['Important point'],
                        'production_tasks' => ['Create draft'],
                        'risks_or_missing_information' => ['Missing audience'],
                    ]),
                ]],
            ]],
        ], 200),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Original Title',
        'content_type' => 'Newsletter',
        'brief' => 'Original brief',
        'notes' => 'Original notes',
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertCreated();

    $generation = ContentGeneration::first();

    expect($generation->response)->toMatchArray([
        'suggested_title' => 'Generated Title',
        'content_brief' => 'Generated Brief',
    ])
        ->and($generation->prompt)
        ->toContain('Original Title')
        ->toContain('Newsletter')
        ->toContain('Original brief')
        ->toContain('Original notes');
});

it('handles a provider failure safely', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'error' => [
                'message' => 'SECRET PROVIDER ERROR',
            ],
        ], 500),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response
        ->assertStatus(502)
        ->assertJson([
            'message' => 'The content plan could not be generated. Please try again later.',
        ])
        ->assertDontSee('SECRET PROVIDER ERROR')
        ->assertDontSee('test-openai-key');

    $generation = ContentGeneration::first();

    expect($generation->status)->toBe('failed')
        ->and($generation->error_code)->toBe('provider_error');
});

it('handles invalid provider output safely', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => 'not valid json',
                ]],
            ]],
        ], 200),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response
        ->assertStatus(502)
        ->assertJson([
            'message' => 'The content plan could not be generated. Please try again later.',
        ]);

    $generation = ContentGeneration::first();

    expect($generation->status)->toBe('failed')
        ->and($generation->error_code)->toBe('invalid_json');
});

it('handles an empty provider response safely', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [],
        ], 200),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response
        ->assertStatus(502)
        ->assertJson([
            'message' => 'The content plan could not be generated. Please try again later.',
        ]);

    expect(ContentGeneration::first()->error_code)->toBe('empty_response');
});

it('handles structurally invalid provider output safely', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'suggested_title' => 'Title',
                        'content_brief' => 'Brief',
                    ]),
                ]],
            ]],
        ], 200),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project));

    $response
        ->assertStatus(502)
        ->assertJson([
            'message' => 'The content plan could not be generated. Please try again later.',
        ]);

    $generation = ContentGeneration::first();

    expect($generation->status)->toBe('failed')
        ->and($generation->error_code)->toBe('generation_failed');
});

it('stores provider token usage', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'suggested_title' => 'Title',
                        'content_brief' => 'Brief',
                        'outline' => [],
                        'key_points' => [],
                        'production_tasks' => [],
                        'risks_or_missing_information' => [],
                    ]),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 123,
                'output_tokens' => 456,
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertCreated();

    $generation = ContentGeneration::first();

    expect($generation->input_tokens)->toBe(123)
        ->and($generation->output_tokens)->toBe(456);
});

it('only includes allowed project information in the provider prompt', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'text' => json_encode([
                        'suggested_title' => 'Generated Title',
                        'content_brief' => 'Generated Brief',
                        'outline' => [],
                        'key_points' => [],
                        'production_tasks' => [],
                        'risks_or_missing_information' => [],
                    ]),
                ]],
            ]],
        ], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'private@example.com',
    ]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Allowed Project Title',
        'content_type' => 'Newsletter',
        'brief' => 'Allowed project brief',
        'notes' => 'Allowed project notes',
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertCreated();

    Http::assertSent(function ($request) use ($user) {
        $prompt = $request['input'][1]['content'];

        return str_contains($prompt, 'Allowed Project Title')
            && str_contains($prompt, 'Newsletter')
            && str_contains($prompt, 'Allowed project brief')
            && str_contains($prompt, 'Allowed project notes')
            && ! str_contains($prompt, 'private@example.com')
            && ! str_contains($prompt, 'test-openai-key')
            && ! str_contains($prompt, 'password')
            && ! str_contains($prompt, (string) $user->id);
    });
});

it('requires authentication to generate a content plan', function () {
    Http::fake();

    $project = Project::factory()->create();

    $response = $this
        ->postJson(route('projects.generations.store', $project));

    $response->assertUnauthorized();

    expect(ContentGeneration::count())->toBe(0);

    Http::assertNothingSent();
});

<?php

use App\Jobs\GenerateContentPlan;
use App\Models\ContentGeneration;
use App\Models\Project;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.model' => 'gpt-4o-mini',
    ]);
});

function boundedPlan(): array
{
    return [
        'suggested_title' => 'Title',
        'content_brief' => 'Brief',
        'outline' => [['heading' => 'Heading', 'purpose' => 'Purpose']],
        'key_points' => ['Point'],
        'production_tasks' => ['Task'],
        'risks_or_missing_information' => ['Risk'],
    ];
}

it('rejects oversized stored project fields before creating work', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => str_repeat('T', 201),
        'content_type' => 'Article',
        'brief' => 'Brief',
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.store', $project))
        ->assertUnprocessable();

    expect(ContentGeneration::count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rejects oversized regeneration instructions before creating work', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'response' => boundedPlan(),
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.regenerate', [$project, $generation]), [
            'instructions' => str_repeat('I', 2001),
        ])
        ->assertUnprocessable();

    expect(ContentGeneration::count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rejects oversized draft fields without changing the saved draft', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $original = boundedPlan();
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'response' => $original,
        'draft' => $original,
    ]);

    $invalid = $original;
    $invalid['suggested_title'] = str_repeat('T', 201);

    $this->actingAs($user)
        ->putJson(route('projects.generations.draft', [$project, $generation]), [
            'draft' => $invalid,
        ])
        ->assertUnprocessable();

    expect($generation->fresh()->draft)->toBe($original);
});

it('rejects a source plan that exceeds content bounds', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $source = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'response' => [...boundedPlan(), 'key_points' => [str_repeat('P', 1001)]],
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.regenerate', [$project, $source]), [
            'instructions' => 'Change the angle.',
        ])
        ->assertUnprocessable();

    expect(ContentGeneration::count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rejects a composed regeneration prompt above 30000 characters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $source = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'response' => [
            ...boundedPlan(),
            'key_points' => array_fill(0, 20, str_repeat('P', 1000)),
            'production_tasks' => array_fill(0, 20, str_repeat('T', 1000)),
            'risks_or_missing_information' => array_fill(0, 20, str_repeat('R', 1000)),
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.regenerate', [$project, $source]), [
            'instructions' => 'Change the angle.',
        ])
        ->assertUnprocessable();

    expect(ContentGeneration::count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

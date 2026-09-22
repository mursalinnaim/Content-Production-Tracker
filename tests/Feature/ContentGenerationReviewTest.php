<?php

use App\Jobs\GenerateContentPlan;
use App\Models\AcceptedContentPlan;
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

function reviewPlan(): array
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

function completedReviewGeneration(Project $project, array $overrides = []): ContentGeneration
{
    return ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'prompt' => 'Original saved prompt',
        'model' => 'gpt-4o-mini',
        'response' => reviewPlan(),
        ...$overrides,
    ]);
}

it('accepts the saved draft as an exact snapshot without calling OpenAI or the queue', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = completedReviewGeneration($project, [
        'draft' => [
            ...reviewPlan(),
            'suggested_title' => 'Edited title',
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $generation]));

    $response
        ->assertOk()
        ->assertJsonPath('accepted_content_plan.source_generation_id', $generation->id)
        ->assertJsonPath('accepted_content_plan.content.suggested_title', 'Edited title');

    $accepted = AcceptedContentPlan::first();

    expect($accepted)->not->toBeNull()
        ->and($accepted->project_id)->toBe($project->id)
        ->and($accepted->content)->toBe($generation->draft)
        ->and($accepted->accepted_at)->not->toBeNull();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('accepts the original response when no draft exists', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = completedReviewGeneration($project);

    $this
        ->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $generation]))
        ->assertOk()
        ->assertJsonPath('accepted_content_plan.content', reviewPlan());

    expect(AcceptedContentPlan::first()->content)->toBe($generation->response);
});

it('repeating acceptance of the same generation is a no-op', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = completedReviewGeneration($project);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $generation]))
        ->assertOk();

    $acceptedBefore = AcceptedContentPlan::firstOrFail();
    $acceptedAt = $acceptedBefore->accepted_at;
    $content = $acceptedBefore->content;

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $generation]))
        ->assertOk()
        ->assertJsonPath('accepted_content_plan.id', $acceptedBefore->id);

    $acceptedAfter = AcceptedContentPlan::firstOrFail();

    expect(AcceptedContentPlan::count())->toBe(1)
        ->and($acceptedAfter->id)->toBe($acceptedBefore->id)
        ->and($acceptedAfter->accepted_at->equalTo($acceptedAt))->toBeTrue()
        ->and($acceptedAfter->content)->toBe($content);
});

it('repeated acceptance replaces the current selection without creating history rows', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $first = completedReviewGeneration($project);
    $second = completedReviewGeneration($project, [
        'response' => [
            ...reviewPlan(),
            'suggested_title' => 'Second title',
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $first]))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $second]))
        ->assertOk();

    expect(AcceptedContentPlan::count())->toBe(1)
        ->and(AcceptedContentPlan::first()->source_generation_id)->toBe($second->id)
        ->and(AcceptedContentPlan::first()->content['suggested_title'])->toBe('Second title');
});

it('rejects acceptance for another user and a wrong-project generation', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
    $otherGeneration = completedReviewGeneration($otherProject);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$otherProject, $otherGeneration]))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $otherGeneration]))
        ->assertNotFound();

    expect(AcceptedContentPlan::count())->toBe(0);
});

it('rejects acceptance of an incomplete generation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'processing',
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $generation]))
        ->assertUnprocessable();

    expect(AcceptedContentPlan::count())->toBe(0);
});

it('loads the accepted plan only for its owning project', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);

    $generation = completedReviewGeneration($project);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $generation]))
        ->assertOk();

    $this->actingAs($user)
        ->getJson(route('projects.accepted-plan', [$project]))
        ->assertOk()
        ->assertJsonPath('accepted_content_plan.source_generation_id', $generation->id);

    $this->actingAs($otherUser)
        ->getJson(route('projects.accepted-plan', [$project]))
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson(route('projects.accepted-plan', [$otherProject]))
        ->assertNotFound();
});

it('rejects unauthenticated access to every review endpoint', function () {
    $project = Project::factory()->create();
    $generation = completedReviewGeneration($project);

    $requests = [
        fn () => $this->getJson(route('projects.generations.history', [$project])),
        fn () => $this->getJson(route('projects.generations.show', [$project, $generation])),
        fn () => $this->getJson(route('projects.accepted-plan', [$project])),
        fn () => $this->putJson(
            route('projects.generations.draft', [$project, $generation]),
            ['draft' => reviewPlan()],
        ),
        fn () => $this->postJson(route('projects.generations.accept', [$project, $generation])),
        fn () => $this->postJson(
            route('projects.generations.regenerate', [$project, $generation]),
            ['instructions' => 'Change the angle.'],
        ),
    ];

    foreach ($requests as $request) {
        $request()->assertUnauthorized();
    }

    expect(AcceptedContentPlan::count())->toBe(0)
        ->and(ContentGeneration::count())->toBe(1);
});

it('rejects another user and wrong-project generations for every review endpoint', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $sameOwnerProject = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
    $generation = completedReviewGeneration($project);
    $sameOwnerGeneration = completedReviewGeneration($sameOwnerProject);
    $otherGeneration = completedReviewGeneration($otherProject);

    $requests = fn (User $actor, Project $requestedProject, ContentGeneration $requestedGeneration) => [
        fn () => $this->actingAs($actor)->getJson(route('projects.generations.history', [$requestedProject])),
        fn () => $this->actingAs($actor)->getJson(route('projects.generations.show', [$requestedProject, $requestedGeneration])),
        fn () => $this->actingAs($actor)->getJson(route('projects.accepted-plan', [$requestedProject])),
        fn () => $this->actingAs($actor)->putJson(
            route('projects.generations.draft', [$requestedProject, $requestedGeneration]),
            ['draft' => reviewPlan()],
        ),
        fn () => $this->actingAs($actor)->postJson(route('projects.generations.accept', [$requestedProject, $requestedGeneration])),
        fn () => $this->actingAs($actor)->postJson(
            route('projects.generations.regenerate', [$requestedProject, $requestedGeneration]),
            ['instructions' => 'Change the angle.'],
        ),
    ];

    foreach ($requests($user, $project, $generation) as $request) {
        $request()->assertSuccessful();
    }

    $sameOwnerRequests = $requests($user, $project, $sameOwnerGeneration);

    foreach ([1, 3, 4, 5] as $index) {
        $sameOwnerRequests[$index]()->assertNotFound();
    }

    $requests($user, $project, $sameOwnerGeneration)[0]()->assertSuccessful();
    $requests($user, $project, $sameOwnerGeneration)[2]()->assertSuccessful();

    foreach ($requests($otherUser, $project, $generation) as $request) {
        $request()->assertNotFound();
    }

    foreach ($requests($user, $otherProject, $otherGeneration) as $request) {
        $request()->assertNotFound();
    }
});

it('creates a new pending regeneration from saved draft instructions without provider or queue work in the request', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Project title',
        'content_type' => 'Newsletter',
        'brief' => 'Project brief',
        'notes' => 'Project notes',
    ]);
    $generation = completedReviewGeneration($project, [
        'draft' => [
            ...reviewPlan(),
            'suggested_title' => 'Edited source',
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(
            route('projects.generations.regenerate', [$project, $generation]),
            ['instructions' => 'Make the outline more concise.'],
        );

    $response
        ->assertStatus(202)
        ->assertJsonPath('generation.status', 'pending')
        ->assertJsonPath('generation.source_generation_id', $generation->id)
        ->assertJsonPath('generation.regeneration_instructions', 'Make the outline more concise.')
        ->assertJsonPath('generation.model', 'gpt-4o-mini');

    $newGeneration = ContentGeneration::findOrFail($response->json('generation.id'));

    expect($newGeneration->prompt)
        ->toContain('Edited source')
        ->toContain('Make the outline more concise.')
        ->toContain('Project title')
        ->and($newGeneration->id)->not->toBe($generation->id)
        ->and($generation->response)->toBe(reviewPlan())
        ->and($generation->draft['suggested_title'])->toBe('Edited source');

    Queue::assertPushed(GenerateContentPlan::class);
    Http::assertNothingSent();
});

it('rejects blank regeneration instructions without creating work', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = completedReviewGeneration($project);

    $this->actingAs($user)
        ->postJson(
            route('projects.generations.regenerate', [$project, $generation]),
            ['instructions' => '   '],
        )
        ->assertUnprocessable();

    expect(ContentGeneration::count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rejects regeneration for an ineligible or unauthorized generation', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
    $processing = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'status' => 'processing',
    ]);
    $otherGeneration = completedReviewGeneration($otherProject);

    $this->actingAs($user)
        ->postJson(
            route('projects.generations.regenerate', [$project, $processing]),
            ['instructions' => 'Change the title.'],
        )
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson(
            route('projects.generations.regenerate', [$project, $otherGeneration]),
            ['instructions' => 'Change the title.'],
        )
        ->assertNotFound();

    expect(ContentGeneration::where('project_id', $project->id)->count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('returns the existing active generation instead of replacing its saved prompt', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $source = completedReviewGeneration($project);
    $active = ContentGeneration::factory()->create([
        'project_id' => $project->id,
        'source_generation_id' => $source->id,
        'status' => 'processing',
        'prompt' => 'Existing active prompt',
        'regeneration_instructions' => 'Existing instructions',
        'model' => 'gpt-4o-mini',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('projects.generations.regenerate', [$project, $source]),
        ['instructions' => 'New instructions that must not replace the active work.'],
    );

    $response
        ->assertStatus(202)
        ->assertJsonPath('generation.id', $active->id)
        ->assertJsonPath('regeneration_queued', false)
        ->assertJsonPath(
            'message',
            'A generation is already in progress. Your instructions were not queued.',
        );

    expect(ContentGeneration::count())->toBe(2)
        ->and($active->fresh()->prompt)->toBe('Existing active prompt')
        ->and($active->fresh()->regeneration_instructions)->toBe('Existing instructions');

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rejects unknown draft fields and unknown outline fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $generation = completedReviewGeneration($project);

    $invalidDraft = [
        ...reviewPlan(),
        'unexpected' => 'not allowed',
        'outline' => [
            [
                'heading' => 'Introduction',
                'purpose' => 'Introduce the topic.',
                'unexpected' => 'not allowed',
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson(
            route('projects.generations.draft', [$project, $generation]),
            ['draft' => $invalidDraft],
        )
        ->assertUnprocessable();

    expect($generation->fresh()->draft)->toBeNull();
});

it('keeps the original AI response unchanged when a draft is saved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $original = reviewPlan();
    $generation = completedReviewGeneration($project, ['response' => $original]);

    $draft = [
        ...$original,
        'suggested_title' => 'Edited title',
    ];

    $this->actingAs($user)
        ->putJson(
            route('projects.generations.draft', [$project, $generation]),
            ['draft' => $draft],
        )
        ->assertOk()
        ->assertJsonPath('generation.draft.suggested_title', 'Edited title');

    expect($generation->fresh()->response)->toBe($original)
        ->and($generation->fresh()->draft)->toBe($draft);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('marks only the currently accepted generation as accepted in history', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $first = completedReviewGeneration($project);
    $second = completedReviewGeneration($project, [
        'response' => [
            ...reviewPlan(),
            'suggested_title' => 'Second title',
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $first]))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $second]))
        ->assertOk();

    $response = $this->actingAs($user)
        ->getJson(route('projects.generations.history', [$project]))
        ->assertOk();

    $history = collect($response->json('generations'));

    expect($history->firstWhere('id', $first->id)['is_accepted'])->toBeFalse()
        ->and($history->firstWhere('id', $second->id)['is_accepted'])->toBeTrue()
        ->and($history->firstWhere('id', $first->id))->not->toHaveKey('response')
        ->and($history->firstWhere('id', $first->id))->not->toHaveKey('model');
});

it('does not overwrite an accepted snapshot when a later generation is created', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $source = completedReviewGeneration($project);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $source]))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(
            route('projects.generations.regenerate', [$project, $source]),
            ['instructions' => 'Try a different angle.'],
        )
        ->assertStatus(202);

    expect($project->fresh()->acceptedContentPlan->source_generation_id)
        ->toBe($source->id)
        ->and($project->fresh()->acceptedContentPlan->content)
        ->toBe($source->response);

    Queue::assertPushed(GenerateContentPlan::class);
    Http::assertNothingSent();
});

it('preserves the source draft and accepted snapshot when regeneration fails', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(
            ['error' => 'provider unavailable'],
            500,
        ),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $source = completedReviewGeneration($project, [
        'draft' => [
            ...reviewPlan(),
            'suggested_title' => 'Saved source draft',
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('projects.generations.accept', [$project, $source]))
        ->assertOk();

    $response = $this->actingAs($user)->postJson(
        route('projects.generations.regenerate', [$project, $source]),
        ['instructions' => 'Try a different angle.'],
    );

    $regeneration = ContentGeneration::findOrFail(
        $response->json('generation.id'),
    );

    (new GenerateContentPlan($regeneration->id))
        ->handle(app(OpenAIService::class));

    expect($regeneration->fresh()->status)->toBe('failed')
        ->and($source->fresh()->draft['suggested_title'])
        ->toBe('Saved source draft')
        ->and($project->fresh()->acceptedContentPlan->content['suggested_title'])
        ->toBe('Saved source draft');

    Http::assertSentCount(1);
});

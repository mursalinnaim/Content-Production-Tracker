<?php

use AppModelsContentGeneration;
use AppModelsProject;
use AppModelsUser;
use IlluminateSupportFacadesRateLimiter;

beforeEach(function () {
    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.model' => 'gpt-4o-mini',
        'generation.rate_limit.max_attempts' => 5,
        'generation.rate_limit.decay_seconds' => 60,
    ]);

    RateLimiter::clear('generation-submissions:user:1');
});

it('shares five generation submissions per minute across initial generation and regeneration', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Project',
        'content_type' => 'Article',
        'brief' => 'Brief',
    ]);

    $this->actingAs($user);

    foreach (range(1, 5) as $i) {
        $response = $this->postJson(route('projects.generations.store', $project));
        expect($response->status())->toBe(202);
    }

    $response = $this->postJson(route('projects.generations.store', $project));

    $response->assertStatus(429)
        ->assertJsonPath('code', 'generation_rate_limited')
        ->assertJsonStructure(['message', 'retry_after'])
        ->assertHeader('Retry-After');

    expect(ContentGeneration::query()->count())->toBe(1);
});

it('counts a submission before later project validation fails', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => '',
        'content_type' => 'Article',
        'brief' => 'Brief',
    ]);

    $this->actingAs($user);

    foreach (range(1, 5) as $i) {
        $this->postJson(route('projects.generations.store', $project))
            ->assertStatus(422);
    }

    $this->postJson(route('projects.generations.store', $project))
        ->assertStatus(429);
});

it('does not consume the shared submission allowance for history or acceptance reads', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->getJson(route('projects.generations.history', $project))->assertOk();
    $this->getJson(route('projects.accepted-plan', $project))->assertOk();

    expect(RateLimiter::attempts('generation-submissions:user:'.$user->id))->toBe(0);
});

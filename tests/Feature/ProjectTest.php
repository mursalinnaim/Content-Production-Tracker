<?php

use App\Models\Project;
use App\Models\User;

it('allows a user to have many projects', function () {
    $user = User::factory()->create();

    $projects = Project::factory()->count(2)->create([
        'user_id' => $user->id,
    ]);

    expect($user->projects)
        ->toHaveCount(2)
        ->each->toBeInstanceOf(Project::class);
});

it('allows a project to belong to a user', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($project->user->is($user))->toBeTrue();
});

it('deletes a users projects when the user is deleted', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
    ]);

    $user->delete();

    expect(Project::find($project->id))->toBeNull();
});

it('blocks guests from accessing the projects page', function () {
    $this->get('/projects')
        ->assertRedirect('/login');
});

it('allows authenticated users to access the projects page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk();
});

it('shows the authenticated users projects', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Test Project',
    ]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->where('projects.0.id', $project->id)
            ->where('projects.0.title', 'My Test Project')
        );
});

it('does not show projects belonging to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownProject = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Project',
    ]);

    Project::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other Users Project',
    ]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects', function ($projects) use ($ownProject) {
                return collect($projects)->pluck('id')->contains($ownProject->id)
                    && collect($projects)->pluck('title')->doesntContain('Other Users Project');
            })
        );
});

it('shows projects newest first', function () {
    $user = User::factory()->create();

    $oldProject = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Older Project',
        'created_at' => now()->subDays(2),
    ]);

    $newProject = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Newer Project',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.id', $newProject->id)
            ->where('projects.1.id', $oldProject->id)
        );
});
it('shows an empty projects list when the user has no projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects', [])
        );
});

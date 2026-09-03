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

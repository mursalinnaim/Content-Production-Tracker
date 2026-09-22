<?php

use App\Http\Controllers\ContentGenerationController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard', [
        'internship' => [
            'projectName' => 'Content Production Tracker',
            'currentDay' => 'Day 1',
            'status' => 'Environment ready',
            'message' => 'I built and verified this page.',
        ],
    ])->name('dashboard');

    Route::get('/projects', [ProjectController::class, 'index'])
        ->name('projects.index');

    Route::post(
        '/projects/{project}/generations',
        [ContentGenerationController::class, 'store']
    )->name('projects.generations.store');

    Route::get(
        '/projects/{project}/generations/{generation}',
        [ContentGenerationController::class, 'show']
    )->name('projects.generations.show');

    Route::get(
        '/projects/{project}/generations',
        [ContentGenerationController::class, 'history']
    )->name('projects.generations.history');

    Route::get(
        '/projects/{project}/accepted-plan',
        [ContentGenerationController::class, 'accepted']
    )->name('projects.accepted-plan');

    Route::post(
        '/projects/{project}/generations/{generation}/accept',
        [ContentGenerationController::class, 'accept']
    )->name('projects.generations.accept');

    Route::post(
        '/projects/{project}/generations/{generation}/regenerate',
        [ContentGenerationController::class, 'regenerate']
    )->name('projects.generations.regenerate');

    Route::put(
        '/projects/{project}/generations/{generation}/draft',
        [ContentGenerationController::class, 'saveDraft']
    )->name('projects.generations.draft');
});

require __DIR__.'/settings.php';

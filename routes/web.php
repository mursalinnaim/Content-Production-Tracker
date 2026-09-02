<?php

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
});

require __DIR__.'/settings.php';

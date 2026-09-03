<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = $request->user()
            ->projects()
            ->latest()
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }
}

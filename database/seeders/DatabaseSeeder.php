<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $demoUser = User::factory()->create([
            'name' => 'Intern Test',
            'email' => 'intern@example.test',
            'password' => Hash::make('password'),
        ]);
        Project::factory()
            ->count(10)
            ->create([
                'user_id' => $demoUser->id,
            ]);
    }
}

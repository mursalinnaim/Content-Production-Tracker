<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'content_type' => fake()->randomElement([
                'Ebook',
                'Blog post',
                'Newsletter',
                'Social post',
            ]),
            'status' => fake()->randomElement([
                'Draft',
                'In progress',
                'Review',
                'Complete',
            ]),
            'due_date' => fake()->dateTimeBetween('tomorrow', '+30 days')->format('Y-m-d'),
            'brief' => fake()->paragraph(),
            'notes' => fake()->paragraph(),
        ];
    }
}

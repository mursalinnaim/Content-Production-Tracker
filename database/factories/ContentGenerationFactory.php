<?php

namespace Database\Factories;

use App\Models\ContentGeneration;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentGeneration>
 */
class ContentGenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => 'completed',
            'prompt' => fake()->paragraph(),
            'response' => [
                'suggested_title' => fake()->sentence(4),
                'content_brief' => fake()->paragraph(),
                'outline' => [
                    [
                        'heading' => fake()->sentence(3),
                        'purpose' => fake()->sentence(),
                    ],
                ],
                'key_points' => [
                    fake()->sentence(),
                    fake()->sentence(),
                ],
                'production_tasks' => [
                    fake()->sentence(),
                    fake()->sentence(),
                ],
                'risks_or_missing_information' => [
                    fake()->sentence(),
                ],
            ],
            'model' => 'gpt-4o-mini',
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(100, 1000),
            'error_code' => null,
        ];
    }
}

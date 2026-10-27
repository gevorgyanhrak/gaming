<?php

namespace Database\Factories;

use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPage>
 */
class ContentPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(1),
            'title' => ['en' => fake()->sentence(3)],
            'body' => ['en' => fake()->paragraphs(2, true)],
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}

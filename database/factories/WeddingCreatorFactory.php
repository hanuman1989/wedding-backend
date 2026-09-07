<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingCreator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeddingCreator>
 */
class WeddingCreatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'creator_type' => 'bride',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => '+1'.fake()->numerify('##########'),
            'fathers_name' => fake()->name(),
            'mothers_name' => fake()->name(),
        ];
    }
}

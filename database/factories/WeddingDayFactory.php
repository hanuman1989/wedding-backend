<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeddingDay>
 */
class WeddingDayFactory extends Factory
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
            'day_number' => 1,
            'wedding_day_date' => fake()->date(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'post_code' => fake()->postcode(),
            'landmark_near' => null,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}

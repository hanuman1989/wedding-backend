<?php

namespace Database\Factories;

use App\Models\WeddingDay;
use App\Models\WeddingDayEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeddingDayEvent>
 */
class WeddingDayEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wedding_day_id' => WeddingDay::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'is_music_or_dancing' => false,
            'dress_code' => 'Traditional',
            'venue_name' => fake()->company(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'post_code' => fake()->postcode(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'sort_order' => 0,
        ];
    }
}

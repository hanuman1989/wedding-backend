<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wedding>
 */
class WeddingFactory extends Factory
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
            'creator_type' => 'bride',
            'creator_type_other' => null,
            'description' => null,
            'video_url' => null,
            'number_of_days' => null,
            'food_observance' => null,
            'is_alcohol_offered' => null,
            'main_languages' => null,
            'status' => Wedding::STATUS_DRAFT,
            'current_step' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ];
    }
}

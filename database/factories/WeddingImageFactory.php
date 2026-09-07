<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeddingImage>
 */
class WeddingImageFactory extends Factory
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
            'image' => 'weddings/'.fake()->uuid().'.jpg',
            'disk' => 'public',
            'original_name' => 'wedding.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'sort_order' => 0,
        ];
    }
}

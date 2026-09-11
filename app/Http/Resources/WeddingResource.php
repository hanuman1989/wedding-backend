<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creators = $this->relationLoaded('creators')
            ? $this->creators->keyBy('creator_type')
            : collect();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'creator_type' => $this->creator_type,
            'creator_type_other' => $this->creator_type_other,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'creators' => $this->creators ? WeddingCreatorResource::collection($this->creators) : null,
            'bride' => $creators->has('bride') ? new WeddingCreatorResource($creators->get('bride')) : null,
            'groom' => $creators->has('groom') ? new WeddingCreatorResource($creators->get('groom')) : null,
            'number_of_days' => $this->number_of_days,
            'food_observance' => $this->food_observance,
            'is_alcohol_offered' => $this->is_alcohol_offered,
            'wedding_days' => WeddingDayResource::collection($this->whenLoaded('days')),
            'images' => WeddingImageResource::collection($this->whenLoaded('images')),
            'main_languages' => $this->main_languages,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'submitted_at' => $this->submitted_at,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

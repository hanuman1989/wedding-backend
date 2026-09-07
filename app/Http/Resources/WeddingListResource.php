<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingListResource extends JsonResource
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
        $coverImage = $this->relationLoaded('images') ? $this->images->first() : null;
        $firstDay = $this->relationLoaded('days') ? $this->days->first() : null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'creator_type' => $this->creator_type,
            'bride_name' => $creators->has('bride')
                ? trim($creators->get('bride')->first_name.' '.$creators->get('bride')->last_name)
                : null,
            'groom_name' => $creators->has('groom')
                ? trim($creators->get('groom')->first_name.' '.$creators->get('groom')->last_name)
                : null,
            'cover_image' => $coverImage ? new WeddingImageResource($coverImage) : null,
            'number_of_days' => $this->number_of_days,
            'first_wedding_date' => $firstDay?->wedding_day_date?->toDateString(),
            'city' => $firstDay?->city,
            'state' => $firstDay?->state,
            'main_languages' => $this->main_languages,
            'images_count' => $this->whenCounted('images'),
            'created_at' => $this->created_at,
        ];
    }
}

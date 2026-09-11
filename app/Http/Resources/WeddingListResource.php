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
        $coverImage = $this->relationLoaded('thumbnail') ? $this->thumbnail : null;
        $firstDay = $this->relationLoaded('days') ? $this->days->first() : null;
        $lastDay = $this->relationLoaded('days') ? $this->days->last() : null;
        $locations = $this->relationLoaded('days')
            ? ($this->days->pluck('city')->filter()->unique()->implode(', ') ?: null)
            : null;
        $firstWeddingDate = $firstDay?->wedding_day_date?->format('d M Y');
        $lastWeddingDate = $lastDay?->wedding_day_date?->format('d M Y');
        $weddingDates = $firstWeddingDate && $lastWeddingDate
            ? ($firstWeddingDate === $lastWeddingDate
                ? $firstWeddingDate
                : $firstWeddingDate.' - '.$lastWeddingDate)
            : null;
        $coupleName = match ($this->creator_type) {
            'other' => $creators->has('bride') && $creators->has('groom')
                ? trim($creators->get('bride')->first_name.' '.$creators->get('bride')->last_name)
                    .' & '.trim($creators->get('groom')->first_name.' '.$creators->get('groom')->last_name)
                : null,
            'bride' => $creators->has('groom')
                ? trim($this->first_name.' '.$this->last_name)
                    .' & '.trim($creators->get('groom')->first_name.' '.$creators->get('groom')->last_name)
                : null,
            'groom' => $creators->has('bride')
                ? trim($this->first_name.' '.$this->last_name)
                    .' & '.trim($creators->get('bride')->first_name.' '.$creators->get('bride')->last_name)
                : null,
            default => null,
        };

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
            'couple_name' => $coupleName,
            'cover_image' => $coverImage ? asset('storage/'. $coverImage->image) : null,
            'number_of_days' => $this->number_of_days,
            'food_observance'=> $this->food_observance,
            'is_alcohol_offered' => $this->is_alcohol_offered,
            'first_wedding_date' => $firstDay?->wedding_day_date?->toDateString(),
            'last_wedding_date' => $lastDay?->wedding_day_date?->toDateString(),
            'wedding_dates' => $weddingDates,
            'locations' => $locations,
            'state' => $firstDay?->state,
            'images_count' => $this->whenCounted('images'),
            'created_at' => $this->created_at?->format('d M Y'),
        ];
    }
}

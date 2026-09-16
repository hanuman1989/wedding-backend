<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDayExpired = $this->wedding_day_date?->isPast() ?? false;
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'wedding_day_date' => $this->wedding_day_date?->toDateString(),
            'wedding_day_time' => $this->wedding_day_time
                ? substr((string) $this->wedding_day_time, 0, 5)
                : null,
            'wedding_day_format' => $this->wedding_day_date?->format('l, d M Y'),
            'venue_title' => $this->venue_title,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'post_code' => $this->post_code,
            'landmark_near' => $this->landmark_near,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'isDayExpired' => $isDayExpired,
            'location' => collect([
                    $this->venue_title,
                    $this->address_line_1,
                    $this->address_line_2,
                    $this->city,
                    $this->state,
                    $this->post_code,
                ])
                    ->filter()
                    ->implode(', '),
            'wedding_day_events' => WeddingDayEventResource::collection($this->whenLoaded('events')),
        ];
    }
}

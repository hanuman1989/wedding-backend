<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingBookingDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venue_title' => $this->venue_title,
            'location' => collect([
                $this->weddingDay->venue_title,
                $this->weddingDay->address_line_1,
                $this->weddingDay->address_line_2,
                $this->weddingDay->city,
                $this->weddingDay->state,
                $this->weddingDay->post_code,
            ])
                ->filter()
                ->implode(', '),
        ];
    }
}

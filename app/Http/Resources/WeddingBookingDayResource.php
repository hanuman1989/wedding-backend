<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WeddingBookingDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /*
         * wedding_day_time is a raw "time" column string, not a Carbon cast.
         */
        $weddingDayTime = $this->weddingDay->wedding_day_time
            ? Carbon::parse($this->weddingDay->wedding_day_time)
            : null;

        return [
            'id' => $this->id,
            'wedding_day_date' => $this->weddingDay?->wedding_day_date?->toDateString(),
            'wedding_day_time' => $this->weddingDay->wedding_day_time
                ? substr((string) $this->weddingDay->wedding_day_time, 0, 5)
                : null,
            'wedding_day_format' => $this->weddingDay?->wedding_day_date
                ? Carbon::parse($this->weddingDay->wedding_day_date)->format('l, d M Y')
                : null,
            'wedding_day_time_format' => $weddingDayTime?->format('h:i A'),
            'venue_title' => $this->weddingDay->venue_title,
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
            'booking_day_events' => WeddingDayEventResource::collection($this->weddingDay->events),
        ];
    }
}

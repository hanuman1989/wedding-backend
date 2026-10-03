<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WeddingBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $firstBookingDay = $this->days->first();
        $weddingDayTime = $firstBookingDay?->weddingDay?->wedding_day_time
            ? Carbon::parse($firstBookingDay->weddingDay->wedding_day_time)
            : null;

        $bookingDays = $this->days->sortBy(
            fn ($day) => $day->weddingDay?->wedding_day_date
        );
        $firstBookingDate = $bookingDays->first()?->weddingDay?->wedding_day_date?->format('l, d M Y');
        $lastBookingDate = $bookingDays->last()?->weddingDay?->wedding_day_date?->format('l, d M Y');

        $bookingDaysCount = $bookingDays->count();
        $bookingEventsCount = $bookingDays->sum(
            fn ($day) => $day->weddingDay?->events?->count() ?? 0
        );
        $bookingDaysEventsCount = sprintf(
            '%d %s %d %s',
            $bookingDaysCount,
            $bookingDaysCount === 1 ? 'Day' : 'Days',
            $bookingEventsCount,
            $bookingEventsCount === 1 ? 'event' : 'events'
        );

        $bookingDates = $lastBookingDate && $lastBookingDate !== $firstBookingDate
            ? "{$firstBookingDate} - {$lastBookingDate}"
            : $firstBookingDate;

        $bookingLocations = $this->whenLoaded('days')
            ? ($this->days->pluck('weddingDay.city')->filter()->unique()->implode(', ') ?: null)
            : null;

        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'user_id' => $this->user_id,
            'wedding_id' => $this->wedding_id,
            'status' => $this->status,
            'wedding_booking_date' => $firstBookingDay?->weddingDay?->wedding_day_date?->format('l, d M Y'),
            'wedding_booking_time' => $weddingDayTime?->format('h:i A'),
            'booking_days_dates' => $bookingDates,
            'booking_locations' => $bookingLocations,
            'booking_days_events_count' => $bookingDaysEventsCount,
            'wedding' => new WeddingDetailResource($this->wedding),
            'user' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
            'visiting_from' => $this->visiting_from,
            'heard_about' => $this->heard_about,
            'number_of_travelers' => $this->number_of_travelers,
            'price_per_person' => $this->price_per_person,
            'subtotal' => $this->subtotal,
            'platform_fee' => $this->platform_fee,
            'payment_fee' => $this->payment_fee,
            'total_amount' => $this->total_amount,
            // Amount owed to the wedding host once the platform fee is withheld.
            'payout_amount' => (float) $this->total_amount - (float) $this->platform_fee,
            'currency' => strtoupper(
                $this->currency
            ),
            'pricing' => [
                'price_per_person' => $this->price_per_person,
                'subtotal' => $this->subtotal,
                'platform_fee' => $this->platform_fee,
                'payment_fee' => $this->payment_fee,
                'total_amount' => $this->total_amount,
                'payout_amount' => (float) $this->total_amount - (float) $this->platform_fee,
                'currency' => strtoupper(
                    $this->currency
                ),
            ],
            'wedding_days' => WeddingBookingDayResource::collection(
                $this->whenLoaded('days')
            ),
            'payment' => [
                'payment_id' => $this->payment?->id,
                'status' => $this->payment?->status,
                'payment_intent_id' => $this->payment?->payment_intent_id,
                'client_secret' => $this->payment?->client_secret,
                'paid_at' => $this->payment?->paid_at?->toISOString(),
            ],
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->format('l, d M Y'),
        ];
    }
}

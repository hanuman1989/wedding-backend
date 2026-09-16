<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingBookingResource extends JsonResource
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
            'booking_number' => $this->booking_number,
            'status' => $this->status,
            'wedding' => new WeddingDetailResource($this->wedding),
            'primary_guest' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
            'visiting_from' => $this->visiting_from,
            'heard_about' => $this->heard_about,
            'number_of_travelers' => $this->number_of_travelers,
            'pricing' => [
                'price_per_person' => $this->price_per_person,
                'subtotal' => $this->subtotal,
                'platform_fee' => $this->platform_fee,
                'payment_fee' => $this->payment_fee,
                'total_amount' => $this->total_amount,
                'currency' => strtoupper(
                    $this->currency
                ),
            ],
            'wedding_days' => WeddingBookingDayResource::collection(
                $this->whenLoaded('days')
            ),
            'payment' => [
                'status' => $this->payment?->status,
                'payment_intent_id' => $this->payment?->payment_intent_id,
                'client_secret' => $this->payment?->client_secret,
                'paid_at' => $this->payment?->paid_at?->toISOString(),
            ],
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

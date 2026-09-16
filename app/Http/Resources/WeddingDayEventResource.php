<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingDayEventResource extends JsonResource
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
            'title' => $this->title,
            'event_time' =>  $this->event_time
                ? substr((string) $this->event_time, 0, 5)
                : null,
            'description' => $this->description,
            'is_music_or_dancing' => $this->is_music_or_dancing,
            'is_alcohol_offered' => $this->is_alcohol_offered,
            'dress_code' => $this->dress_code,
            'sort_order' => $this->sort_order,
        ];
    }
}

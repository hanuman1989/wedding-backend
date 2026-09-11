<?php

namespace App\Services;

use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WeddingDetailsSynchronizer
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function synchronize(Wedding $wedding, array $attributes): Wedding
    {
        return DB::transaction(function () use ($wedding, $attributes): Wedding {
            $wedding->update(Arr::only($attributes, [
                'number_of_days',
                'food_observance',
                'is_alcohol_offered',
            ]));

            foreach ($attributes['wedding_days'] as $dayPayload) {
                $weddingDay = $this->saveDay($wedding, $dayPayload);

                foreach ($dayPayload['wedding_day_events'] as $eventPayload) {
                    $eventData = [
                        'title' => $eventPayload['title'],
                        'description' => $eventPayload['description'],
                        'is_music_or_dancing' => $eventPayload['is_music_or_dancing'] ?? null,
                        'dress_code' => $eventPayload['dress_code'],
                    ];

                    if (($eventPayload['id'] ?? null) === null) {
                        $weddingDay->events()->create($eventData);
                    } else {
                        $weddingDayEvent = $weddingDay->events()->findOrFail($eventPayload['id']);
                        $weddingDayEvent->update($eventData);
                    }
                }
            }

            $wedding->forceFill([
                'current_step' => max($wedding->current_step, 4),
            ])->save();

            return $wedding;
        });
    }

    /**
     * @param  array<string, mixed>  $dayPayload
     */
    private function saveDay(Wedding $wedding, array $dayPayload): WeddingDay
    {
        $dayData = [
            'wedding_day_date' => $dayPayload['wedding_day_date'],
            'wedding_day_time' => $dayPayload['wedding_day_time'] ?? null,
            'address_line_1' => $dayPayload['address_line_1'] ?? null,
            'address_line_2' => $dayPayload['address_line_2'] ?? null,
            'city' => $dayPayload['city'],
            'state' => $dayPayload['state'] ?? null,
            'post_code' => $dayPayload['post_code'] ?? null,
            'landmark_near' => $dayPayload['landmark_near'] ?? null,
            'latitude' => $dayPayload['latitude'] ?? null,
            'longitude' => $dayPayload['longitude'] ?? null,
        ];

        if (($dayPayload['id'] ?? null) === null) {
            return $wedding->days()->create($dayData);
        }

        $weddingDay = $wedding->days()->findOrFail($dayPayload['id']);
        $weddingDay->update($dayData);

        return $weddingDay;
    }
}

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
                'main_languages',
            ]));

            $submittedDayIds = [];

            foreach ($attributes['days'] as $dayPayload) {
                $weddingDay = $this->saveDay($wedding, $dayPayload);
                $submittedDayIds[] = $weddingDay->id;

                $submittedEventIds = [];

                foreach ($dayPayload['events'] as $eventIndex => $eventPayload) {
                    $eventAttributes = Arr::only($eventPayload, [
                        'title',
                        'description',
                        'start_time',
                        'end_time',
                        'is_music_or_dancing',
                        'dress_code',
                        'venue_name',
                        'address_line_1',
                        'address_line_2',
                        'city',
                        'state',
                        'country',
                        'post_code',
                        'latitude',
                        'longitude',
                    ]);
                    $eventAttributes['sort_order'] = $eventPayload['sort_order'] ?? $eventIndex;

                    if (isset($eventPayload['id'])) {
                        $event = $weddingDay->events()
                            ->whereKey($eventPayload['id'])
                            ->firstOrFail();
                        $event->update($eventAttributes);
                    } else {
                        $event = $weddingDay->events()->create($eventAttributes);
                    }

                    $submittedEventIds[] = $event->id;
                }

                $weddingDay->events()
                    ->whereNotIn('id', $submittedEventIds)
                    ->delete();
            }

            $wedding->days()
                ->whereNotIn('id', $submittedDayIds)
                ->delete();
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
        $dayAttributes = Arr::only($dayPayload, [
            'day_number',
            'wedding_day_date',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'country',
            'post_code',
            'landmark_near',
            'latitude',
            'longitude',
        ]);

        if (isset($dayPayload['id'])) {
            $weddingDay = $wedding->days()
                ->whereKey($dayPayload['id'])
                ->firstOrFail();
            $weddingDay->update($dayAttributes);

            return $weddingDay;
        }

        return $wedding->days()->create($dayAttributes);
    }
}

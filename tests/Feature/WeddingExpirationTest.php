<?php

use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

test('weddings expire only when every wedding day is before today', function (array $dates, bool $expected) {
    $this->travelTo(Carbon::parse('2026-10-03 23:59:59', 'UTC'));
    $wedding = Wedding::factory()->make(['user_id' => 1]);
    $days = array_map(
        fn (?string $date): WeddingDay => WeddingDay::factory()->make([
            'wedding_id' => 1, 'wedding_day_date' => $date,
        ]),
        $dates,
    );
    $wedding->setRelation('days', new Collection($days));

    expect($wedding->isExpired())->toBe($expected);
})->with([
    'all days in the past' => [['2026-10-01', '2026-10-02'], true],
    'today alone' => [['2026-10-03'], false],
    'past and today' => [['2026-10-02', '2026-10-03'], false],
    'future day' => [['2026-10-04'], false],
    'past and future' => [['2026-10-02', '2026-10-04'], false],
    'no days' => [[], false],
    'missing date' => [[null], false],
]);

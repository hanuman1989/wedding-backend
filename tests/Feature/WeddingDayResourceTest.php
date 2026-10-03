<?php

use App\Http\Resources\WeddingDayResource;
use App\Models\WeddingDay;
use Illuminate\Support\Carbon;

test('wedding days expire only before today', function (?string $date, bool $expected) {
    $this->travelTo(Carbon::parse('2026-10-03 23:59:59', 'UTC'));
    $day = WeddingDay::factory()->make(['wedding_id' => 1, 'wedding_day_date' => $date]);

    $data = (new WeddingDayResource($day))->resolve();

    expect($data['is_day_expired'])->toBe($expected);
})->with([
    'yesterday' => ['2026-10-02', true],
    'today remains available until midnight' => ['2026-10-03', false],
    'tomorrow' => ['2026-10-04', false],
    'missing date' => [null, false],
]);

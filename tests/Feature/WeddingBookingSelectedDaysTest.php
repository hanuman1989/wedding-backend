<?php

use App\Http\Controllers\WeddingBookingController;
use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('selected wedding days remain available today and in the future', function (string $date) {
    $this->travelTo(Carbon::parse('2026-10-03 23:59:59', 'UTC'));
    $wedding = Wedding::factory()->create();
    $day = WeddingDay::factory()->make(['wedding_id' => $wedding->id, 'wedding_day_date' => $date]);
    unset($day->day_number);
    $day->save();
    $method = new ReflectionMethod(WeddingBookingController::class, 'getSelectedDays');

    $days = $method->invoke(app(WeddingBookingController::class), $wedding, [$day->id]);

    expect($days->modelKeys())->toBe([$day->id]);
})->with(['today' => '2026-10-03', 'future' => '2026-10-04']);

test('selected wedding days before today are rejected even alongside today', function () {
    $this->travelTo(Carbon::parse('2026-10-03 12:00:00', 'UTC'));
    $wedding = Wedding::factory()->create();
    $yesterday = WeddingDay::factory()->make(['wedding_id' => $wedding->id, 'wedding_day_date' => '2026-10-02']);
    unset($yesterday->day_number);
    $yesterday->save();
    $today = WeddingDay::factory()->make(['wedding_id' => $wedding->id, 'wedding_day_date' => '2026-10-03']);
    unset($today->day_number);
    $today->save();
    $method = new ReflectionMethod(WeddingBookingController::class, 'getSelectedDays');

    try {
        $method->invoke(app(WeddingBookingController::class), $wedding, [$yesterday->id, $today->id]);
        $this->fail('Expired wedding days should be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'selected_days' => ['One or more selected wedding days have expired.'],
        ]);
    }
});

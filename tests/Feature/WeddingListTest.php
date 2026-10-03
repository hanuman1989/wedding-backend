<?php

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('wedding list applies food observance only when provided', function (array $filters, int $expectedCount) {
    $this->freezeTime();
    foreach (['vegetarian', 'non-vegetarian'] as $observance) {
        $wedding = Wedding::factory()->create(['food_observance' => $observance]);
        $day = WeddingDay::factory()->make(['wedding_id' => $wedding->id, 'wedding_day_date' => today()]);
        unset($day->day_number);
        $day->save();
    }

    $response = $this->getJson('/api/wedding-list?'.http_build_query($filters));

    $response->assertOk()->assertJsonCount($expectedCount, 'data')
        ->assertJsonPath('pagination.total', $expectedCount);
    if ($expectedCount === 1) {
        $response->assertJsonPath('data.0.food_observance', 'vegetarian');
    }
})->with([
    'provided' => [['food_observance' => 'vegetarian'], 1],
    'missing' => [[], 2],
    'empty' => [['food_observance' => ''], 2],
    'no match' => [['food_observance' => 'unknown'], 0],
]);

test('wedding list includes the first and last wedding dates', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create([
        'created_at' => '2026-08-10 12:00:00',
    ]);
    WeddingDay::factory()->for($wedding)->create([
        'wedding_day_date' => '2026-10-12',
        'city' => 'Jaipur',
    ]);
    WeddingDay::factory()->for($wedding)->create([
        'wedding_day_date' => '2026-10-14',
        'city' => 'Delhi',
    ]);
    WeddingDay::factory()->for($wedding)->create([
        'wedding_day_date' => '2026-10-16',
        'city' => 'Udaipur',
    ]);
    WeddingDay::factory()->for($wedding)->create([
        'wedding_day_date' => '2026-10-18',
        'city' => 'Jaipur',
    ]);
    $token = $user->createToken('wedding-list-test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/my-weddings')
        ->assertOk()
        ->assertJsonPath('data.data.0.first_wedding_date', '2026-10-12')
        ->assertJsonPath('data.data.0.last_wedding_date', '2026-10-18')
        ->assertJsonPath('data.data.0.locations', 'Jaipur, Delhi, Udaipur')
        ->assertJsonPath('data.data.0.created_at', '10 Aug 2026')
        ->assertJsonPath('data.data.0.wedding_dates', '12 Oct 2026 - 18 Oct 2026');
});

test('wedding list shows one date when the first and last wedding dates match', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    WeddingDay::factory()->for($wedding)->create([
        'wedding_day_date' => '2026-10-12',
    ]);
    WeddingDay::factory()->for($wedding)->create([
        'wedding_day_date' => '2026-10-12',
    ]);
    $token = $user->createToken('wedding-list-single-date-test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/my-weddings')
        ->assertOk()
        ->assertJsonPath('data.data.0.wedding_dates', '12 Oct 2026');
});

test('wedding list includes pagination details', function () {
    Wedding::factory()->count(2)->create()->each(function (Wedding $wedding): void {
        WeddingDay::factory()->for($wedding)->create([
            'wedding_day_date' => today(),
        ]);
    });

    $this->getJson('/api/wedding-list?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('pagination.current_page', 2)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('pagination.per_page', 1)
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('pagination.from', 2)
        ->assertJsonPath('pagination.to', 2)
        ->assertJsonPath('pagination.prev_page_url', fn (string $url): bool => str_contains($url, 'page=1'))
        ->assertJsonPath('pagination.next_page_url', null);
});

test('wedding list only includes weddings with today or future wedding days', function () {
    $pastWedding = Wedding::factory()->create();
    WeddingDay::factory()->for($pastWedding)->create([
        'wedding_day_date' => today()->subDay(),
    ]);

    $todayWedding = Wedding::factory()->create();
    WeddingDay::factory()->for($todayWedding)->create([
        'wedding_day_date' => today(),
    ]);

    $futureWedding = Wedding::factory()->create();
    WeddingDay::factory()->for($futureWedding)->create([
        'wedding_day_date' => today()->addDay(),
    ]);

    $this->getJson('/api/wedding-list')
        ->assertOk()
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonCount(2, 'data.data');
});

test('wedding list filters by a custom start date', function () {
    $before = Wedding::factory()->create();
    WeddingDay::factory()->for($before)->create([
        'wedding_day_date' => '2026-09-01',
    ]);

    $after = Wedding::factory()->create();
    WeddingDay::factory()->for($after)->create([
        'wedding_day_date' => '2026-09-25',
    ]);

    $this->getJson('/api/wedding-list?date=2026-09-20')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $after->id);
});

test('wedding list filters between a start date and an end date', function () {
    $tooEarly = Wedding::factory()->create();
    WeddingDay::factory()->for($tooEarly)->create([
        'wedding_day_date' => '2026-09-10',
    ]);

    $withinRange = Wedding::factory()->create();
    WeddingDay::factory()->for($withinRange)->create([
        'wedding_day_date' => '2026-09-22',
    ]);

    $tooLate = Wedding::factory()->create();
    WeddingDay::factory()->for($tooLate)->create([
        'wedding_day_date' => '2026-10-05',
    ]);

    $this->getJson('/api/wedding-list?date=2026-09-20&end_date=2026-09-30')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $withinRange->id);
});

test('wedding list orders by nearest distance without duplicating weddings', function () {
    $nearWedding = Wedding::factory()->create();
    WeddingDay::factory()->for($nearWedding)->create([
        'wedding_day_date' => today()->addDay(),
        'latitude' => 0.01,
        'longitude' => 0.01,
    ]);
    WeddingDay::factory()->for($nearWedding)->create([
        'wedding_day_date' => today()->addDays(2),
        'latitude' => 0.02,
        'longitude' => 0.02,
    ]);

    $farWedding = Wedding::factory()->create();
    WeddingDay::factory()->for($farWedding)->create([
        'wedding_day_date' => today()->addDay(),
        'latitude' => 40,
        'longitude' => 70,
    ]);

    $response = $this->getJson('/api/wedding-list?latitude=0&longitude=0')
        ->assertOk()
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('data.data.0.id', $nearWedding->id)
        ->assertJsonPath('data.data.1.id', $farWedding->id);

    $ids = collect($response->json('data.data'))->pluck('id');
    expect($ids)->toEqual($ids->unique());

    expect($response->json('data.data.0.distance_km'))
        ->toBeFloat()
        ->toBeLessThan($response->json('data.data.1.distance_km'));
});

<?php

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

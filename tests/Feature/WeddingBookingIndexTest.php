<?php

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use App\Models\WeddingImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createWeddingBookingForTest(Wedding $wedding, User $user): WeddingBooking
{
    return WeddingBooking::create([
        'wedding_id' => $wedding->id,
        'user_id' => $user->id,
        'booking_number' => 'IWI-'.strtoupper(Str::random(10)),
        'status' => WeddingBooking::STATUS_PENDING,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'phone' => '1234567890',
        'number_of_travelers' => 2,
        'price_per_person' => 250,
        'subtotal' => 500,
        'total_amount' => 500,
        'currency' => 'usd',
    ]);
}

test('wedding booking index only returns the authenticated user\'s bookings', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $wedding = Wedding::factory()->create();
    $otherWedding = Wedding::factory()->create();

    $booking = createWeddingBookingForTest($wedding, $user);
    createWeddingBookingForTest($otherWedding, $otherUser);

    $token = $user->createToken('booking-index-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/wedding-bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $booking->id);
});

test('wedding booking index filters by wedding_id', function () {
    $user = User::factory()->create();
    $weddingA = Wedding::factory()->create();
    $weddingB = Wedding::factory()->create();

    $bookingA = createWeddingBookingForTest($weddingA, $user);
    createWeddingBookingForTest($weddingB, $user);

    $token = $user->createToken('booking-index-filter-test')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/wedding-bookings?wedding_id={$weddingA->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bookingA->id);
});

test('wedding booking index eager loads the related wedding and its images', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->create();
    WeddingImage::factory()->for($wedding)->create();

    createWeddingBookingForTest($wedding, $user);

    $token = $user->createToken('booking-index-images-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/wedding-bookings')
        ->assertOk()
        ->assertJsonPath('data.0.wedding.id', $wedding->id)
        ->assertJsonCount(1, 'data.0.wedding.images');
});

test('wedding booking index returns only the latest booking when limit is given', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->create();

    createWeddingBookingForTest($wedding, $user);
    $latestBooking = createWeddingBookingForTest($wedding, $user);

    $token = $user->createToken('booking-index-limit-test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/wedding-bookings?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $latestBooking->id);

    expect($response->json('pagination'))->toBeNull();
});

<?php

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('successful payment sets paid_at and preserves it on repeated webhooks', function (?string $existingPaidAt) {
    $this->travelTo(new DateTimeImmutable('2026-10-02 12:00:00'));
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    $user = User::factory()->create();
    $wedding = Wedding::factory()->create();
    $booking = WeddingBooking::create([
        'wedding_id' => $wedding->id,
        'user_id' => $user->id,
        'booking_number' => 'IWI-PAIDAT',
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
    $payment = $booking->payment()->create([
        'payment_intent_id' => 'pi_paidAtTest',
        'status' => 'pending',
        'amount' => 50000,
        'currency' => 'usd',
        'paid_at' => $existingPaidAt,
    ]);
    $payload = json_encode([
        'id' => 'evt_paidAtTest',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_paidAtTest',
            'object' => 'payment_intent',
            'amount' => 50000,
            'currency' => 'usd',
        ]],
    ], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

    $this->call('POST', '/api/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
    ], $payload)->assertOk();

    expect($payment->fresh()->paid_at->format('Y-m-d H:i:s'))
        ->toBe($existingPaidAt ?? '2026-10-02 12:00:00');
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
})->with([null, '2026-10-01 09:00:00']);

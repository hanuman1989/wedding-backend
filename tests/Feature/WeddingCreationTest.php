<?php

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an authenticated user can create a draft wedding', function () {
    $user = User::factory()->create();
    $token = $user->createToken('wedding-test-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/weddings', [
            'creator_type' => 'bride',
            'first_name' => 'Anika',
            'last_name' => 'Shah',
            'phone' => '+919876543210',
            'email' => 'anika@example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.status', Wedding::STATUS_DRAFT);

    $wedding = Wedding::query()->firstOrFail();
    $user->refresh();

    expect($wedding->user_id)->toBe($user->id)
        ->and($wedding->status)->toBe(Wedding::STATUS_DRAFT)
        ->and($user->is_host)->toBeTrue();
});

test('wedding creation preserves an existing host status', function () {
    $user = User::factory()->create(['is_host' => true]);
    $token = $user->createToken('existing-host-wedding-test-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/weddings', [
            'creator_type' => 'bride',
            'first_name' => 'Anika',
            'last_name' => 'Shah',
            'phone' => '+919876543210',
            'email' => 'anika@example.com',
        ])
        ->assertCreated();

    expect($user->fresh()->is_host)->toBeTrue();
});

test('wedding creation validates the request payload', function () {
    $user = User::factory()->create();
    $token = $user->createToken('wedding-validation-test-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/weddings', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'creator_type',
            'first_name',
            'last_name',
            'phone',
            'email',
        ]);

    expect(Wedding::query()->count())->toBe(0);
});

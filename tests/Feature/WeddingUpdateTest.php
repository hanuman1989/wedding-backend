<?php

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can partially update their wedding with patch', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create([
        'first_name' => 'Anika',
        'last_name' => 'Shah',
        'email' => 'anika@example.com',
        'phone' => '+919876543210',
    ]);
    $token = $user->createToken('wedding-patch-test-token')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/weddings/{$wedding->id}", [
            'first_name' => 'Aarav',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.first_name', 'Aarav')
        ->assertJsonPath('data.last_name', 'Shah')
        ->assertJsonPath('message', 'Wedding updated successfully.');
});

test('put requires the complete wedding payload', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    $token = $user->createToken('wedding-put-validation-test-token')->plainTextToken;

    $this->withToken($token)
        ->putJson("/api/weddings/{$wedding->id}", [
            'first_name' => 'Aarav',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'creator_type',
            'last_name',
            'phone',
            'email',
        ]);
});

test('a user cannot update another users wedding', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($owner)->create();
    $token = $user->createToken('wedding-update-owner-test-token')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/weddings/{$wedding->id}", [
            'first_name' => 'Unauthorized',
        ])
        ->assertNotFound()
        ->assertJson([
            'status' => false,
            'data' => null,
            'message' => 'Wedding not found.',
        ]);
});

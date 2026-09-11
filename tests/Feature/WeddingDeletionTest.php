<?php

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingCreator;
use App\Models\WeddingDay;
use App\Models\WeddingDayEvent;
use App\Models\WeddingImage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can delete a wedding and its relation records', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    $creator = WeddingCreator::factory()->for($wedding)->create();
    $image = WeddingImage::factory()->for($wedding)->create();
    $day = WeddingDay::factory()->for($wedding)->create();
    $event = WeddingDayEvent::factory()->for($day)->create();
    $token = $user->createToken('wedding-delete-test-token')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/weddings/{$wedding->id}")
        ->assertOk()
        ->assertJson([
            'status' => true,
            'data' => null,
            'message' => 'Wedding deleted successfully.',
        ]);

    $this->assertDatabaseMissing('weddings', ['id' => $wedding->id]);
    $this->assertDatabaseMissing('wedding_creators', ['id' => $creator->id]);
    $this->assertDatabaseMissing('wedding_images', ['id' => $image->id]);
    $this->assertDatabaseMissing('wedding_days', ['id' => $day->id]);
    $this->assertDatabaseMissing('wedding_day_events', ['id' => $event->id]);
});

test('a user cannot delete another users wedding', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($owner)->create();
    $token = $user->createToken('wedding-delete-owner-test-token')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/weddings/{$wedding->id}")
        ->assertNotFound()
        ->assertJson([
            'status' => false,
            'data' => null,
            'message' => 'Wedding not found.',
        ]);

    $this->assertDatabaseHas('weddings', ['id' => $wedding->id]);
});

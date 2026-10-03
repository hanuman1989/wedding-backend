<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingCreator;
use App\Models\WeddingDay;
use App\Models\WeddingDayEvent;
use App\Models\WeddingImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('an admin can delete a wedding with its related records and images', function () {
    Storage::fake('public');
    Storage::fake('local');
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    $otherWedding = Wedding::factory()->create();
    $creator = WeddingCreator::factory()->for($wedding)->create();
    $image = WeddingImage::factory()->for($wedding)->create();
    $localImage = WeddingImage::factory()->for($wedding)->create(['disk' => 'local']);
    $otherImage = WeddingImage::factory()->for($otherWedding)->create();
    $day = WeddingDay::factory()->for($wedding)
        ->afterMaking(function (WeddingDay $day): void {
            unset($day->day_number);
        })->create();
    $event = WeddingDayEvent::factory()->for($day, 'weddingDay')
        ->afterMaking(function (WeddingDayEvent $event): void {
            $event->setRawAttributes(collect($event->getAttributes())->only([
                'wedding_day_id', 'title', 'description', 'is_music_or_dancing',
                'dress_code', 'sort_order',
            ])->all());
        })->create();
    Storage::disk('public')->put($image->image, 'image');
    Storage::disk('local')->put($localImage->image, 'image');
    Storage::disk('public')->put($otherImage->image, 'other image');

    $this->deleteJson("/api/admin/weddings/{$wedding->id}")
        ->assertOk()
        ->assertExactJson([
            'status' => true,
            'data' => null,
            'message' => 'Wedding deleted successfully.',
        ]);

    $this->assertModelMissing($wedding);
    $this->assertModelMissing($creator);
    $this->assertModelMissing($image);
    $this->assertModelMissing($localImage);
    $this->assertModelMissing($day);
    $this->assertModelMissing($event);
    $this->assertModelExists($otherWedding);
    $this->assertModelExists($otherImage);
    Storage::disk('public')->assertMissing($image->image);
    Storage::disk('local')->assertMissing($localImage->image);
    Storage::disk('public')->assertExists($otherImage->image);
});

test('wedding deletion returns 403 without authentication', function () {
    $wedding = Wedding::factory()->create();

    $this->deleteJson("/api/admin/weddings/{$wedding->id}")->assertForbidden();

    $this->assertModelExists($wedding);
});

test('wedding deletion returns 403 for a regular user', function () {
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/admin/weddings/{$wedding->id}")->assertForbidden();

    $this->assertModelExists($wedding);
});

test('wedding deletion returns 404 for a missing wedding', function () {
    Sanctum::actingAs(new AdminUser);

    $this->deleteJson('/api/admin/weddings/999999')->assertNotFound();
});

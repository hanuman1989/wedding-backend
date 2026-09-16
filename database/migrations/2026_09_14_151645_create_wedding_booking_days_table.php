<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wedding_booking_days', function (Blueprint $table) {
            $table->id();
             $table->foreignId('booking_id')
                ->constrained('wedding_bookings')
                ->cascadeOnDelete();

            $table->foreignId('wedding_day_id')
                ->constrained('wedding_days')
                ->cascadeOnDelete();

            $table->unique([
                'booking_id',
                'wedding_day_id'
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wedding_booking_days');
    }
};

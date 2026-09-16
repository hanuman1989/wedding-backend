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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_booking_id')
                ->constrained('wedding_bookings')
                ->cascadeOnDelete();
            $table->string('provider')->default('stripe');
            $table->string('payment_intent_id')->unique();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->json('response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

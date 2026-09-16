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
        Schema::create('wedding_bookings', function (Blueprint $table) {
            $table->id();
             $table->foreignId('wedding_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('booking_number')->unique();
            $table->string('status')->default('pending');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('visiting_from')->nullable();
            $table->string('heard_about')->nullable();
            $table->unsignedInteger('number_of_travelers');
            $table->decimal('price_per_person', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('payment_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wedding_bookings');
    }
};

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
        Schema::table('wedding_day_events', function (Blueprint $table) {
            $table->time('event_time')->nullable()->index()->after("title");
            $table->boolean('is_alcohol_offered')->nullable()->default(false)->after("dress_code");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wedding_day_events', function (Blueprint $table) {
            $table->dropColumn([
                'event_time',
                'is_alcohol_offered',
            ]);
        });
    }
};

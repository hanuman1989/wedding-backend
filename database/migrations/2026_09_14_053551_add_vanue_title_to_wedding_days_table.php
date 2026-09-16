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
        Schema::table('wedding_days', function (Blueprint $table) {
            $table->string('venue_title', 200)->after("wedding_day_time")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wedding_days', function (Blueprint $table) {
            $table->dropColumn([
                'venue_title',
            ]);
        });
    }
};

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
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('creator_type', 20)->index();
            $table->string('creator_type_other', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->unsignedTinyInteger('number_of_days')->nullable();
            $table->string('food_observance', 100)->nullable();
            $table->boolean('is_alcohol_offered')->nullable();
            $table->json('main_languages')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};

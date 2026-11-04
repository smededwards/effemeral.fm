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
        Schema::create('album_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spotify_saved_album_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('heard_tracks');
            $table->unsignedInteger('total_tracks');
            $table->unsignedTinyInteger('completion_percentage');
            $table->unique(['recommendation_run_id', 'spotify_saved_album_id'], 'album_recommendations_run_album_unique');
            $table->unique(['recommendation_run_id', 'position'], 'album_recommendations_run_position_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_recommendations');
    }
};

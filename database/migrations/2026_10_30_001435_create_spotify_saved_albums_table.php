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
        Schema::create('spotify_saved_albums', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_album_id')->unique();
            $table->string('spotify_uri');
            $table->string('spotify_url')->nullable();
            $table->string('name');
            $table->string('album_type');
            $table->unsignedInteger('total_tracks')->nullable();
            $table->string('release_date')->nullable();
            $table->string('release_date_precision')->nullable();
            $table->string('primary_artist_id')->nullable();
            $table->string('primary_artist_name')->nullable();
            $table->json('artists')->nullable();
            $table->json('tracks')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->timestamp('saved_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spotify_saved_albums');
    }
};

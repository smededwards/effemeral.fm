<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalised_name');
            $table->string('spotify_id')->nullable()->unique();
            $table->string('lastfm_mbid')->nullable()->unique();
            $table->timestamps();
            $table->unique('normalised_name');
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('normalised_title');
            $table->string('album_type')->nullable();
            $table->string('spotify_id')->nullable()->unique();
            $table->string('lastfm_mbid')->nullable()->unique();
            $table->string('spotify_uri')->nullable();
            $table->string('spotify_url')->nullable();
            $table->unsignedInteger('total_tracks')->nullable();
            $table->string('release_date')->nullable();
            $table->string('release_date_precision')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('album_artist', function (Blueprint $table) {
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('artist_position')->default(0);
            $table->primary(['album_id', 'artist_id']);
        });

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('normalised_title');
            $table->string('spotify_id')->nullable()->unique();
            $table->string('lastfm_mbid')->nullable()->unique();
            $table->string('spotify_uri')->nullable();
            $table->unsignedSmallInteger('disc_number')->nullable();
            $table->unsignedSmallInteger('track_number')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->unique(['album_id', 'normalised_title', 'disc_number', 'track_number'], 'tracks_album_title_position_unique');
        });

        Schema::create('spotify_album_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->string('collection_type');
            $table->string('spotify_playlist_id')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['album_id', 'collection_type', 'spotify_playlist_id'], 'spotify_album_collections_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spotify_album_collections');
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('album_artist');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('artists');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spotify_playlists', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_playlist_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('spotify_url')->nullable();
            $table->string('owner_id')->nullable();
            $table->boolean('public')->nullable();
            $table->boolean('collaborative')->default(false);
            $table->unsignedInteger('tracks_total')->default(0);
            $table->string('snapshot_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('spotify_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spotify_playlist_id')->constrained('spotify_playlists')->cascadeOnDelete();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->string('spotify_track_id')->nullable();
            $table->string('spotify_uri')->nullable();
            $table->string('track_name')->nullable();
            $table->unsignedInteger('position');
            $table->timestamp('added_at')->nullable();
            $table->boolean('is_local')->default(false);
            $table->timestamps();
            $table->unique(['spotify_playlist_id', 'position'], 'spotify_playlist_items_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spotify_playlist_items');
        Schema::dropIfExists('spotify_playlists');
    }
};

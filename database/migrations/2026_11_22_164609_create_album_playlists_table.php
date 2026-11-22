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
        Schema::create('album_playlists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('spotify_playlist_id')->nullable()->unique();
            $table->string('spotify_playlist_url')->nullable();
            $table->timestamps();
        });

        Schema::create('album_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['album_playlist_id', 'album_id']);
            $table->index(['album_playlist_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_playlist_items');
        Schema::dropIfExists('album_playlists');
    }
};

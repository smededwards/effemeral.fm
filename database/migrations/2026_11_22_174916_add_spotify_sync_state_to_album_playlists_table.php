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
        Schema::table('album_playlists', function (Blueprint $table) {
            $table->json('spotify_managed_track_uris')->nullable();
            $table->json('spotify_pending_track_uris')->nullable();
            $table->string('spotify_sync_status')->default('draft');
            $table->text('spotify_sync_error')->nullable();
            $table->timestamp('spotify_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('album_playlists', function (Blueprint $table) {
            $table->dropColumn([
                'spotify_managed_track_uris',
                'spotify_pending_track_uris',
                'spotify_sync_status',
                'spotify_sync_error',
                'spotify_synced_at',
            ]);
        });
    }
};

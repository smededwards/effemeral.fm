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
        Schema::table('album_playlists', function (Blueprint $table): void {
            $table->timestamp('spotify_sync_started_at')->nullable()->after('spotify_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('album_playlists', function (Blueprint $table): void {
            $table->dropColumn('spotify_sync_started_at');
        });
    }
};

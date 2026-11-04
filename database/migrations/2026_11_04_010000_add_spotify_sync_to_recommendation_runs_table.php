<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_runs', function (Blueprint $table) {
            $table->string('spotify_playlist_id')->nullable();
            $table->string('spotify_playlist_url')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_runs', function (Blueprint $table) {
            $table->dropColumn(['spotify_playlist_id', 'spotify_playlist_url', 'synced_at', 'sync_error']);
        });
    }
};

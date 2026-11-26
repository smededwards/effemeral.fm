<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the optional playlist filter used by an enrichment run.
     */
    public function up(): void
    {
        Schema::table('spotify_album_enrichment_runs', function (Blueprint $table): void {
            $table->string('spotify_playlist_id')->nullable()->after('pause_seconds');
        });
    }

    /**
     * Remove the optional playlist filter from enrichment runs.
     */
    public function down(): void
    {
        Schema::table('spotify_album_enrichment_runs', function (Blueprint $table): void {
            $table->dropColumn('spotify_playlist_id');
        });
    }
};

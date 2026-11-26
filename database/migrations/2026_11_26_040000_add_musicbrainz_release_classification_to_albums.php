<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store MusicBrainz release classification separately from Spotify metadata.
     */
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table): void {
            $table->string('canonical_release_type')->nullable()->after('album_type');
            $table->string('musicbrainz_primary_type')->nullable()->after('musicbrainz_release_group_mbid');
            $table->json('musicbrainz_secondary_types')->nullable()->after('musicbrainz_primary_type');
        });
    }

    /**
     * Remove MusicBrainz release classification fields.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table): void {
            $table->dropColumn([
                'canonical_release_type',
                'musicbrainz_primary_type',
                'musicbrainz_secondary_types',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('location')->nullable()->after('normalised_name');
            $table->boolean('location_manual')->default(false)->after('location');
            $table->boolean('region_manual')->default(false)->after('region');
            $table->string('musicbrainz_mbid')->nullable()->unique()->after('lastfm_mbid');
            $table->timestamp('musicbrainz_checked_at')->nullable()->after('musicbrainz_mbid');
        });

        DB::table('artists')->whereNotNull('region')->update(['region_manual' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropUnique(['musicbrainz_mbid']);
            $table->dropColumn(['location', 'location_manual', 'region_manual', 'musicbrainz_mbid', 'musicbrainz_checked_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spotify_album_import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('next_offset')->default(0)->after('requested_limit');
            $table->unsignedInteger('total_count')->nullable()->after('next_offset');
        });

        Schema::table('spotify_playlist_import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('next_offset')->default(0)->after('requested_limit');
            $table->unsignedInteger('total_count')->nullable()->after('next_offset');
        });
    }

    public function down(): void
    {
        Schema::table('spotify_playlist_import_runs', function (Blueprint $table): void {
            $table->dropColumn(['next_offset', 'total_count']);
        });

        Schema::table('spotify_album_import_runs', function (Blueprint $table): void {
            $table->dropColumn(['next_offset', 'total_count']);
        });
    }
};

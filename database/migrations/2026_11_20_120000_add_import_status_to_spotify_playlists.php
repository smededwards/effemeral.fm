<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spotify_playlists', function (Blueprint $table): void {
            $table->text('last_import_error')->nullable();
            $table->timestamp('last_import_attempted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spotify_playlists', function (Blueprint $table): void {
            $table->dropColumn(['last_import_error', 'last_import_attempted_at']);
        });
    }
};

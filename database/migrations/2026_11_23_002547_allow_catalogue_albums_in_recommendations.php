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
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->dropForeign(['spotify_saved_album_id']);
            $table->unsignedBigInteger('spotify_saved_album_id')->nullable()->change();
            $table->foreign('spotify_saved_album_id')->references('id')->on('spotify_saved_albums')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('album_recommendations')->whereNull('spotify_saved_album_id')->exists()) {
            throw new RuntimeException('Cannot roll back catalogue recommendations while recommendations without saved-album links exist.');
        }

        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->dropForeign(['spotify_saved_album_id']);
            $table->unsignedBigInteger('spotify_saved_album_id')->nullable(false)->change();
            $table->foreign('spotify_saved_album_id')->references('id')->on('spotify_saved_albums')->cascadeOnDelete();
        });
    }
};

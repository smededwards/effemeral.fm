<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scrobbles', function (Blueprint $table) {
            $table->foreignId('artist_id')->nullable()->after('source_key')->constrained()->nullOnDelete();
            $table->foreignId('album_id')->nullable()->after('artist_id')->constrained()->nullOnDelete();
            $table->foreignId('track_id')->nullable()->after('album_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scrobbles', function (Blueprint $table) {
            $table->dropForeign(['artist_id']);
            $table->dropForeign(['album_id']);
            $table->dropForeign(['track_id']);
            $table->dropColumn(['artist_id', 'album_id', 'track_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->foreignId('album_id')->nullable()->after('recommendation_run_id')->constrained()->nullOnDelete();
            $table->unique(['recommendation_run_id', 'album_id'], 'album_recommendations_run_canonical_album_unique');
        });
    }

    public function down(): void
    {
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->dropUnique('album_recommendations_run_canonical_album_unique');
            $table->dropForeign(['album_id']);
            $table->dropColumn('album_id');
        });
    }
};

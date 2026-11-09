<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->string('match_reason')->nullable()->after('completion_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->dropColumn('match_reason');
        });
    }
};

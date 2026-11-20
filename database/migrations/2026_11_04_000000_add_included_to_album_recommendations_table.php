<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->boolean('included')->default(true)->after('completion_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('album_recommendations', function (Blueprint $table) {
            $table->dropColumn('included');
        });
    }
};

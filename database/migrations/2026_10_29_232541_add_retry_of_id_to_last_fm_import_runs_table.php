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
        Schema::table('last_fm_import_runs', function (Blueprint $table) {
            $table->foreignId('retry_of_id')->nullable()->after('id')->constrained('last_fm_import_runs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('last_fm_import_runs', function (Blueprint $table) {
            $table->dropForeign(['retry_of_id']);
            $table->dropColumn('retry_of_id');
        });
    }
};

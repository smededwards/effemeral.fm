<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('last_fm_import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('failed_count')->default(0)->after('skipped_count');
        });
    }

    public function down(): void
    {
        Schema::table('last_fm_import_runs', function (Blueprint $table): void {
            $table->dropColumn('failed_count');
        });
    }
};

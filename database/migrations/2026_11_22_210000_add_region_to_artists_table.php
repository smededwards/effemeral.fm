<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->string('region')->nullable()->after('normalised_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->dropIndex(['region']);
            $table->dropColumn('region');
        });
    }
};

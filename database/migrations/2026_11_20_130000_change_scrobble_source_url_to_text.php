<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scrobbles', function (Blueprint $table): void {
            $table->text('source_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('scrobbles', function (Blueprint $table): void {
            $table->string('source_url')->nullable()->change();
        });
    }
};

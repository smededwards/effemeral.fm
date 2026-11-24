<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the composite provider index within MySQL's utf8mb4 key-length limit.
        Schema::table('catalog_identities', function (Blueprint $table): void {
            $table->string('provider', 32)->change();
            $table->string('entity_type', 32)->change();
            $table->string('identifier_type', 64)->change();
            $table->string('identifier', 191)->change();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_identities', function (Blueprint $table): void {
            $table->string('provider')->change();
            $table->string('entity_type')->change();
            $table->string('identifier_type')->change();
            $table->string('identifier')->change();
        });
    }
};

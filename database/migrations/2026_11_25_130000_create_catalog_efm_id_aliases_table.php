<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table used to retain retired EFMIDs after reconciliation.
     */
    public function up(): void
    {
        Schema::create('catalog_efm_id_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 32);
            $table->string('retired_efm_id', 32)->unique();
            $table->unsignedBigInteger('canonical_entity_id');
            $table->string('canonical_efm_id', 32);
            $table->string('reason');
            $table->timestamps();
            $table->index(['entity_type', 'canonical_entity_id']);
        });
    }

    /**
     * Remove the retired EFMID alias table.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_efm_id_aliases');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the provider-title alias table.
     */
    public function up(): void
    {
        Schema::create('track_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalised_name');
            $table->string('source');
            $table->string('provider_id')->nullable();
            $table->timestamps();
            $table->unique(['track_id', 'source', 'normalised_name'], 'track_aliases_track_source_name_unique');
            $table->unique(['source', 'provider_id'], 'track_aliases_source_provider_unique');
        });
    }

    /**
     * Remove the provider-title alias table.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_aliases');
    }
};

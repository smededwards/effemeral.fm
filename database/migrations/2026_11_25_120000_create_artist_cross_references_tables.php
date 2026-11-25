<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the provider-backed artist cross-reference tables.
     */
    public function up(): void
    {
        Schema::create('artist_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalised_name');
            $table->string('locale')->nullable();
            $table->string('alias_type')->nullable();
            $table->string('source');
            $table->timestamps();
            $table->unique(['artist_id', 'normalised_name', 'source'], 'artist_aliases_artist_name_source_unique');
        });

        Schema::create('artist_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->string('related_provider_id')->nullable();
            $table->string('related_name');
            $table->string('relationship_type');
            $table->string('source');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(
                ['artist_id', 'source', 'related_provider_id', 'relationship_type'],
                'artist_relationships_artist_source_target_type_unique',
            );
            $table->index(['related_name', 'relationship_type']);
        });
    }

    /**
     * Remove the provider-backed artist cross-reference tables.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_relationships');
        Schema::dropIfExists('artist_aliases');
    }
};

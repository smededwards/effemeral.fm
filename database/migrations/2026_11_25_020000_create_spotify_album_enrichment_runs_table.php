<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create persistent progress records for Spotify album enrichment.
     */
    public function up(): void
    {
        Schema::create('spotify_album_enrichment_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->default('priority');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('priority_phase')->default(0);
            $table->unsignedInteger('requested_limit')->default(10);
            $table->unsignedSmallInteger('pause_every')->default(3);
            $table->unsignedSmallInteger('pause_seconds')->default(10);
            $table->unsignedBigInteger('last_album_id')->nullable();
            $table->unsignedInteger('total_candidates')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('enriched_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('failed_album_ids')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove persistent Spotify album enrichment progress.
     */
    public function down(): void
    {
        Schema::dropIfExists('spotify_album_enrichment_runs');
    }
};

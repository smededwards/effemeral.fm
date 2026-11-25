<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create release associations and backfill existing canonical track ownership.
     */
    public function up(): void
    {
        Schema::create('album_track_releases', function (Blueprint $table): void {
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('disc_number')->nullable();
            $table->unsignedSmallInteger('track_number')->nullable();
            $table->timestamps();
            $table->primary(['album_id', 'track_id']);
        });

        DB::table('tracks')->orderBy('id')->chunkById(100, function ($tracks): void {
            DB::table('album_track_releases')->insert($tracks->map(fn ($track): array => [
                'album_id' => $track->album_id,
                'track_id' => $track->id,
                'disc_number' => $track->disc_number,
                'track_number' => $track->track_number,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });
    }

    /**
     * Remove release associations when the migration is rolled back.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_track_releases');
    }
};

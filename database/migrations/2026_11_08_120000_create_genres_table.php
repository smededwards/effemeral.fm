<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalised_name')->unique();
            $table->timestamps();
        });

        Schema::create('artist_genre', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->boolean('suppressed')->default(false);
            $table->timestamps();
            $table->unique(['artist_id', 'genre_id', 'source'], 'artist_genre_artist_genre_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_genre');
        Schema::dropIfExists('genres');
    }
};

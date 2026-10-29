<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scrobbles', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('lastfm');
            $table->string('source_key')->unique();
            $table->string('artist_name');
            $table->string('artist_mbid')->nullable();
            $table->string('track_name');
            $table->string('track_mbid')->nullable();
            $table->string('album_name')->nullable();
            $table->string('album_mbid')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('listened_at')->index();
            $table->boolean('now_playing')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrobbles');
    }
};

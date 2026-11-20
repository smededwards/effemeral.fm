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
        Schema::create('last_fm_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('lastfm');
            $table->string('status')->index();
            $table->timestamp('from_at');
            $table->timestamp('to_at');
            $table->unsignedSmallInteger('requested_limit')->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_fm_import_runs');
    }
};

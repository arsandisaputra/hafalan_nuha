<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Table: page_progress
     * Tracks Tahfidz status ('belum', 'proses', 'hafal') and Muraja'ah review metrics per Mushaf page (1–604).
     */
    public function up(): void
    {
        Schema::create('page_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('page_number')->unique();
            $table->enum('status', ['belum', 'proses', 'hafal'])->default('belum')->index();
            $table->unsignedInteger('review_count')->default(0);
            $table->timestamp('last_reviewed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_progress');
    }
};

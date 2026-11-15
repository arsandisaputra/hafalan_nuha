<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Table: quran_pages_cache
     * Stores normalized Quran.com API v4 responses per Madinah Mushaf page (1–604)
     * for instant and offline-friendly page loads.
     */
    public function up(): void
    {
        Schema::create('quran_pages_cache', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_number')->primary();
            $table->unsignedTinyInteger('juz_number')->index();
            $table->string('surah_names', 255);
            $table->longText('payload_json');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_pages_cache');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Table: page_notes
     * Stores personal Tadabbur & Mind-Link ("Benang Merah") narrative summaries and per-verse anchor cues.
     */
    public function up(): void
    {
        Schema::create('page_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('page_number')->unique();
            $table->text('narrative_summary')->nullable()->comment('Benang merah halaman / alur cerita ayat');
            $table->json('verse_links_json')->nullable()->comment('Optional per-verse personal cues mapped by verse_key');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_notes');
    }
};

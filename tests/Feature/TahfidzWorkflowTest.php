<?php

namespace Tests\Feature;

use App\Models\PageProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TahfidzWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_mushaf_reader_with_page_and_juz_metadata(): void
    {
        $response = $this->get('/mushaf/45');

        $response->assertStatus(200);
        $response->assertSee('Halaman 45 / 604');
        $response->assertSee('Halaman 4 dari 20 di Juz 3');
        $response->assertSee('Al-Baqarah');
    }

    public function test_updates_page_memorization_status(): void
    {
        $response = $this->patchJson('/api/pages/45/progress', [
            'status' => PageProgress::STATUS_HAFAL,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.page_number', 45)
            ->assertJsonPath('data.status', 'hafal');

        $this->assertDatabaseHas('page_progress', [
            'page_number' => 45,
            'status' => 'hafal',
        ]);
    }

    public function test_logs_daily_murajaah_and_increments_review_count(): void
    {
        $this->postJson('/api/pages/12/murajaah')
            ->assertStatus(200)
            ->assertJsonPath('data.review_count', 1)
            ->assertJsonPath('data.status', 'proses');

        $this->postJson('/api/pages/12/murajaah')
            ->assertStatus(200)
            ->assertJsonPath('data.review_count', 2);
    }

    public function test_auto_saves_mind_link_benang_merah_notes(): void
    {
        $payload = [
            'narrative_summary' => 'Ayat 1-3: Sifat orang bertakwa -> Ayat 4-5: Balasan mereka',
            'verse_links_json' => [
                '2:1' => 'Alif Lam Mim',
                '2:2' => 'Petunjuk bagi Muttaqin',
            ],
        ];

        $this->putJson('/api/pages/2/notes', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.page_number', 2)
            ->assertJsonPath('data.narrative_summary', $payload['narrative_summary']);

        $this->assertDatabaseHas('page_notes', [
            'page_number' => 2,
            'narrative_summary' => $payload['narrative_summary'],
        ]);
    }

    public function test_renders_thirty_juz_dashboard_grid(): void
    {
        $this->seed();

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee("Dashboard Tahfidz & Muraja'ah 30 Juz", false);
        $response->assertViewHas('juzGrid', fn (array $grid) => count($grid) === 30);
        $response->assertViewHas('summary', fn (array $summary) => $summary['total_pages'] === 604);
    }
}

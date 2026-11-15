<?php

namespace Database\Seeders;

use App\Models\PageNote;
use App\Models\PageProgress;
use App\Repositories\QuranPageRepository;
use App\Services\QuranApiService;
use App\Support\MushafPageHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial Mushaf page cache,
     * sample Tahfidz progress, and Mind-Link ("Benang Merah") notes.
     */
    public function run(): void
    {
        /** @var QuranApiService $quranApi */
        $quranApi = app(QuranApiService::class);
        /** @var QuranPageRepository $repository */
        $repository = app(QuranPageRepository::class);

        // 1. Seed built-in offline Mushaf pages (Pages 1, 2, 604) into `quran_pages_cache`
        foreach ($quranApi->getBuiltInSeedPages() as $pageNumber => $rawVerses) {
            $positionMeta = MushafPageHelper::getPagePositionMetadata($pageNumber);
            $normalized = $quranApi->normalizePagePayload($pageNumber, $rawVerses, [], $positionMeta);

            $repository->saveCachedPage(
                $pageNumber,
                $normalized['juz_number'],
                $normalized['surah_names'],
                $normalized
            );
        }

        // 2. Seed sample Tahfidz progress & Muraja'ah history
        PageProgress::query()->updateOrCreate(
            ['page_number' => 1],
            [
                'status' => PageProgress::STATUS_HAFAL,
                'review_count' => 12,
                'last_reviewed_at' => Carbon::now()->subDays(1),
            ]
        );

        PageProgress::query()->updateOrCreate(
            ['page_number' => 2],
            [
                'status' => PageProgress::STATUS_PROSES,
                'review_count' => 4,
                'last_reviewed_at' => Carbon::now()->subDays(2),
            ]
        );

        PageProgress::query()->updateOrCreate(
            ['page_number' => 45],
            [
                'status' => PageProgress::STATUS_PROSES,
                'review_count' => 3,
                'last_reviewed_at' => Carbon::now()->subDays(8),
            ]
        );

        PageProgress::query()->updateOrCreate(
            ['page_number' => 604],
            [
                'status' => PageProgress::STATUS_HAFAL,
                'review_count' => 9,
                'last_reviewed_at' => Carbon::now()->subDays(10), // Demonstrates >7 days Muraja'ah alert
            ]
        );

        // 3. Seed sample Mind-Link / Benang Merah notes on Page 2 (Al-Baqarah 1–5) & Page 45 (Al-Baqarah 265–269)
        PageNote::query()->updateOrCreate(
            ['page_number' => 2],
            [
                'narrative_summary' => "Ayat 1: Huruf Muqatha'ah (Alif Lam Mim) sebagai tantangan kemukjizatan.\nAyat 2: Al-Qur'an tiada keraguan, petunjuk bagi orang bertakwa (Muttaqin).\nAyat 3–4: 5 Karakter orang bertakwa (Iman pada yang gaib, mendirikan salat, infak, iman pada kitab terdahulu, yakin pada akhirat).\nAyat 5: Kesimpulan & balasan mereka — mendapat petunjuk dari Rabb dan menjadi orang-orang yang beruntung (al-muflihun).",
                'verse_links_json' => [
                    '2:1' => 'Pembuka surat dengan Alif Lam Mim',
                    '2:2' => 'Definisi fungsi Al-Qur\'an untuk Muttaqin',
                    '2:3' => '3 sifat pertama: Gaib -> Salat -> Infak',
                    '2:4' => '2 sifat berikutnya: Kitab wahyu -> Yakin Akhirat',
                    '2:5' => 'Penutup halaman: Atas petunjuk Rabb & merekalah orang beruntung',
                ],
            ]
        );

        PageNote::query()->updateOrCreate(
            ['page_number' => 45],
            [
                'narrative_summary' => "Ayat 265: Perumpamaan infak ikhlas seperti kebun di dataran tinggi yang berbuah 2x lipat.\nAyat 266: Kontras perumpamaan orang yang amalnya hangus terbakar di masa tua.\nAyat 267: Perintah menginfakkan harta yang baik-baik (thayyibat), bukan yang buruk.\nAyat 268: Bisikan setan (menakuti kefakiran) vs Janji Allah (ampunan & karunia).\nAyat 269: Puncak halaman — Allah menganugerahkan Al-Hikmah (kebaikan yang banyak) bagi Ulul Albab.",
                'verse_links_json' => [
                    '2:265' => 'Kebun di dataran tinggi (rabwah) berbuah 2x lipat',
                    '2:266' => 'Kebun terbakar angin berapi saat usia tua',
                    '2:267' => 'Infak dari yang thayyib, jangan pilih yang khabits',
                    '2:268' => 'Ancaman setan (fakir) vs Janji Allah (ampunan)',
                    '2:269' => 'Anugerah Al-Hikmah untuk Ulul Albab',
                ],
            ]
        );
    }
}

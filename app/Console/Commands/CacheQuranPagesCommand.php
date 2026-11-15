<?php

namespace App\Console\Commands;

use App\Services\QuranApiService;
use App\Support\MushafPageHelper;
use Illuminate\Console\Command;

class CacheQuranPagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran:cache-pages
                            {--from=1 : Halaman awal Mushaf (1-604)}
                            {--to=604 : Halaman akhir Mushaf (1-604)}
                            {--force : Paksa unduh ulang dari Quran.com API v4 meskipun sudah ada di cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengunduh dan menyimpan cache lokal halaman Mushaf Madinah (1–604) dari Quran.com API v4 ke tabel quran_pages_cache untuk akses instan & offline.';

    public function handle(QuranApiService $quranApiService): int
    {
        $from = max(MushafPageHelper::MIN_PAGE, (int) $this->option('from'));
        $to = min(MushafPageHelper::MAX_PAGE, (int) $this->option('to'));
        $force = (bool) $this->option('force');

        if ($from > $to) {
            $this->error("Parameter --from ({$from}) tidak boleh lebih besar dari --to ({$to}).");

            return self::FAILURE;
        }

        $total = ($to - $from) + 1;
        $this->info("Memulai sinkronisasi cache Mushaf Madinah halaman {$from} s/d {$to} ({$total} halaman)...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount = 0;
        $fallbackCount = 0;

        for ($page = $from; $page <= $to; $page++) {
            $payload = $quranApiService->getPageData($page, $force);

            if (! empty($payload['verses']) && empty($payload['is_fallback'])) {
                $successCount++;
            } else {
                $fallbackCount++;
            }

            $bar->advance();
            usleep(120000); // Polite rate-limiting (120ms between requests)
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai! Berhasil menyimpan {$successCount} halaman dari API ({$fallbackCount} menggunakan fallback/offline).");

        return self::SUCCESS;
    }
}

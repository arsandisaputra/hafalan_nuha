<?php

namespace App\Services;

use App\Repositories\QuranPageRepository;
use App\Support\MushafPageHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrates with Quran.com API v4 (https://api.quran.com/api/v4)
 * to fetch Uthmani script, Indonesian translation (Kemenag RI / resource ID 33),
 * and verse-by-verse audio recitations mapped strictly by Mushaf Page Number (1–604).
 *
 * Implements a multi-tier caching strategy:
 * 1. Application Cache (file/database/redis)
 * 2. Database table `quran_pages_cache` (persistent offline-friendly storage)
 * 3. Live Quran.com API v4 fetch with graceful fallback
 */
class QuranApiService
{
    private string $baseUrl;
    private int $translationId;
    private int $defaultReciterId;
    private int $timeout;
    private array $reciters;

    public function __construct(
        private readonly QuranPageRepository $repository
    ) {
        $this->baseUrl = rtrim((string) config('services.quran_api.base_url', 'https://api.quran.com/api/v4'), '/');
        $this->translationId = (int) config('services.quran_api.translation_id', 33);
        $this->defaultReciterId = (int) config('services.quran_api.default_reciter_id', 7);
        $this->timeout = (int) config('services.quran_api.timeout', 15);
        $this->reciters = (array) config('services.quran_api.reciters', []);
    }

    /**
     * Return available Qari / Reciters list.
     */
    public function getSupportedReciters(): array
    {
        return $this->reciters;
    }

    /**
     * Get normalized Mushaf page data (1–604), reading from cache first or fetching from Quran.com API v4.
     */
    public function getPageData(int $pageNumber, bool $forceRefresh = false): array
    {
        $pageNumber = MushafPageHelper::validatePageNumber($pageNumber);
        $cacheKey = "quran_mushaf_page_v1_{$pageNumber}";

        if (! $forceRefresh) {
            // 1. Check fast application cache
            try {
                $cachedMemory = Cache::get($cacheKey);
                if (is_array($cachedMemory) && ! empty($cachedMemory['verses'])) {
                    return $cachedMemory;
                }
            } catch (Throwable) {
                // Continue to DB cache if cache table/driver is not yet initialized
            }

            // 2. Check persistent `quran_pages_cache` table
            try {
                $cachedRow = $this->repository->findCachedPage($pageNumber);
                if ($cachedRow !== null) {
                    $payload = $cachedRow->decoded_payload;
                    if (! empty($payload['verses'])) {
                        $this->putInAppCache($cacheKey, $payload);

                        return $payload;
                    }
                }
            } catch (Throwable $e) {
                Log::warning("Unable to read quran_pages_cache for page {$pageNumber}: " . $e->getMessage());
            }
        }

        // 3. Fetch live from Quran.com API v4 and store in DB + App Cache
        return $this->fetchAndCachePageFromApi($pageNumber, $cacheKey);
    }

    /**
     * Fetch page verses and recitations from Quran.com API v4, normalize, and persist to `quran_pages_cache`.
     */
    public function fetchAndCachePageFromApi(int $pageNumber, ?string $cacheKey = null): array
    {
        $positionMeta = MushafPageHelper::getPagePositionMetadata($pageNumber);

        try {
            // Fetch Uthmani script + Kemenag RI translation (ID 33) + word breakdown
            $versesResponse = Http::timeout($this->timeout)
                ->retry(2, 250)
                ->acceptJson()
                ->get("{$this->baseUrl}/verses/by_page/{$pageNumber}", [
                    'language' => 'id',
                    'words' => 'true',
                    'word_fields' => 'text_uthmani,code_v1,line_number',
                    'translations' => $this->translationId,
                    'fields' => 'text_uthmani,chapter_id,juz_number,page_number,hizb_number,rub_el_hizb_number',
                    'per_page' => 50,
                ]);

            if (! $versesResponse->successful()) {
                throw new \RuntimeException("Quran.com API returned HTTP {$versesResponse->status()} for page {$pageNumber}");
            }

            $rawVerses = $versesResponse->json('verses', []);

            // Fetch primary audio map from Quran.com API v4 for default reciter
            $audioMap = $this->fetchPageAudioMapFromApi($pageNumber, $this->defaultReciterId);

            $normalized = $this->normalizePagePayload($pageNumber, $rawVerses, $audioMap, $positionMeta);

            // Persist to `quran_pages_cache`
            if (! empty($normalized['verses'])) {
                try {
                    $this->repository->saveCachedPage(
                        $pageNumber,
                        $normalized['juz_number'],
                        $normalized['surah_names'],
                        $normalized
                    );
                } catch (Throwable $dbException) {
                    Log::warning("Could not save page {$pageNumber} to quran_pages_cache: " . $dbException->getMessage());
                }

                $this->putInAppCache($cacheKey ?? "quran_mushaf_page_v1_{$pageNumber}", $normalized);
            }

            return $normalized;
        } catch (Throwable $e) {
            Log::error("Failed fetching Quran.com API v4 for page {$pageNumber}: " . $e->getMessage());

            return $this->buildOfflineFallbackPayload($pageNumber, $positionMeta, $e->getMessage());
        }
    }

    /**
     * Fetch verse-by-verse audio URLs from Quran.com API v4 for a specific reciter & page.
     *
     * @return array<string, string> Map of verse_key => full audio URL
     */
    protected function fetchPageAudioMapFromApi(int $pageNumber, int $reciterId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/recitations/{$reciterId}/by_page/{$pageNumber}", [
                    'per_page' => 50,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $audioFiles = $response->json('audio_files', []);
            $map = [];

            foreach ($audioFiles as $audio) {
                $verseKey = $audio['verse_key'] ?? null;
                $url = $audio['url'] ?? null;
                if ($verseKey && $url) {
                    $map[$verseKey] = str_starts_with($url, 'http')
                        ? $url
                        : 'https://verses.quran.com/' . ltrim($url, '/');
                }
            }

            return $map;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Normalize raw Quran.com API v4 response into a clean, page-centric domain structure.
     */
    public function normalizePagePayload(
        int $pageNumber,
        array $rawVerses,
        array $primaryAudioMap,
        array $positionMeta
    ): array {
        $verses = [];
        $surahSet = [];
        $apiJuzNumber = null;

        foreach ($rawVerses as $index => $raw) {
            $verseKey = (string) ($raw['verse_key'] ?? '');
            [$parsedSurahId, $parsedVerseNumber] = array_pad(explode(':', $verseKey), 2, 0);

            $chapterId = (int) ($raw['chapter_id'] ?? $parsedSurahId);
            $verseNumber = (int) ($raw['verse_number'] ?? $parsedVerseNumber);
            $juzNumber = (int) ($raw['juz_number'] ?? $positionMeta['juz_number']);

            if ($apiJuzNumber === null && $juzNumber > 0) {
                $apiJuzNumber = $juzNumber;
            }

            $surahMeta = MushafPageHelper::getSurahMeta($chapterId);
            $surahSet[$chapterId] = $surahMeta;

            $arabicText = trim((string) ($raw['text_uthmani'] ?? ''));

            // Extract and sanitize Indonesian translation (strip footnote <sup foot_note=...>...</sup> tags)
            $rawTranslation = (string) data_get($raw, 'translations.0.text', '');
            $cleanTranslation = $this->sanitizeTranslationHtml($rawTranslation);

            // Split Arabic Uthmani words for Anchor Words Highlight ("Highlight Awal Ayat") & Mode Pancingan Awal
            $anchorSplit = MushafPageHelper::splitAnchorWords($arabicText, 3);

            // Build multi-Qari audio URL map so switching Qari in Alpine.js is instant
            $audioByReciter = $this->buildReciterAudioUrls(
                $chapterId,
                $verseNumber,
                $verseKey,
                $primaryAudioMap[$verseKey] ?? null
            );

            $verses[] = [
                'id' => (int) ($raw['id'] ?? ($index + 1)),
                'index_on_page' => $index,
                'verse_key' => $verseKey,
                'chapter_id' => $chapterId,
                'surah_name' => $surahMeta['name_simple'],
                'surah_name_arabic' => $surahMeta['name_arabic'],
                'surah_name_id' => $surahMeta['name_id'],
                'verse_number' => $verseNumber,
                'is_surah_start' => $verseNumber === 1,
                'show_bismillah' => $verseNumber === 1 && $chapterId !== 1 && $chapterId !== 9,
                'page_number' => $pageNumber,
                'juz_number' => $juzNumber,
                'hizb_number' => (int) ($raw['hizb_number'] ?? 1),
                'text_uthmani' => $arabicText,
                'first_words' => $anchorSplit['first_words'],
                'remaining_words' => $anchorSplit['remaining_words'],
                'total_words' => $anchorSplit['total_words'],
                'translation_id' => $cleanTranslation,
                'audio_url' => $audioByReciter[$this->defaultReciterId] ?? reset($audioByReciter),
                'audio_by_reciter' => $audioByReciter,
            ];
        }

        $surahNamesList = array_map(
            fn (array $s) => $s['name_simple'],
            array_values($surahSet)
        );
        $surahNamesString = ! empty($surahNamesList)
            ? implode(' • ', $surahNamesList)
            : 'Al-Qur\'an';

        $resolvedJuz = $positionMeta['juz_number'] ?: ($apiJuzNumber ?? 1);

        return [
            'page_number' => $pageNumber,
            'juz_number' => $resolvedJuz,
            'page_in_juz' => $positionMeta['page_in_juz'],
            'total_pages_in_juz' => $positionMeta['total_pages_in_juz'],
            'page_label' => $positionMeta['page_label'],
            'juz_position_label' => $positionMeta['juz_position_label'],
            'surah_names' => $surahNamesString,
            'surahs' => array_values($surahSet),
            'verses_count' => count($verses),
            'verses' => $verses,
            'is_fallback' => false,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Build deterministic verse audio URLs for every configured Qari.
     *
     * @return array<int, string>
     */
    public function buildReciterAudioUrls(
        int $chapterId,
        int $verseNumber,
        string $verseKey,
        ?string $apiDefaultAudioUrl = null
    ): array {
        $paddedSurah = str_pad((string) $chapterId, 3, '0', STR_PAD_LEFT);
        $paddedVerse = str_pad((string) $verseNumber, 3, '0', STR_PAD_LEFT);
        $urls = [];

        foreach ($this->reciters as $reciterId => $reciterConfig) {
            $folder = $reciterConfig['everyayah_folder'] ?? 'Alafasy_128kbps';
            $urls[(int) $reciterId] = "https://everyayah.com/data/{$folder}/{$paddedSurah}{$paddedVerse}.mp3";
        }

        if ($apiDefaultAudioUrl !== null && $apiDefaultAudioUrl !== '') {
            $urls[$this->defaultReciterId] = $apiDefaultAudioUrl;
        }

        return $urls;
    }

    /**
     * Strip Quran.com footnote markup (<sup foot_note=12345>1</sup>) and HTML tags cleanly.
     */
    public function sanitizeTranslationHtml(string $rawHtml): string
    {
        if ($rawHtml === '') {
            return '';
        }

        // Remove <sup ...>...</sup> footnote markers
        $withoutFootnotes = preg_replace('/<sup\b[^>]*>.*?<\/sup>/is', '', $rawHtml) ?? $rawHtml;

        // Strip any remaining HTML tags and decode entities
        $plain = strip_tags($withoutFootnotes);
        $decoded = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }

    /**
     * Built-in offline fallback payload so the reader remains functional even without internet
     * before all 604 pages are cached.
     */
    protected function buildOfflineFallbackPayload(int $pageNumber, array $positionMeta, string $errorReason = ''): array
    {
        $builtInPages = $this->getBuiltInSeedPages();
        if (isset($builtInPages[$pageNumber])) {
            return $this->normalizePagePayload(
                $pageNumber,
                $builtInPages[$pageNumber],
                [],
                $positionMeta
            );
        }

        // Provide a clear, non-breaking placeholder verse card if offline on an uncached page
        return [
            'page_number' => $pageNumber,
            'juz_number' => $positionMeta['juz_number'],
            'page_in_juz' => $positionMeta['page_in_juz'],
            'total_pages_in_juz' => $positionMeta['total_pages_in_juz'],
            'page_label' => $positionMeta['page_label'],
            'juz_position_label' => $positionMeta['juz_position_label'],
            'surah_names' => 'Halaman Belum Tersimpan Offline',
            'surahs' => [],
            'verses_count' => 0,
            'verses' => [],
            'is_fallback' => true,
            'offline_message' => "Halaman {$pageNumber} belum tersimpan di cache lokal dan koneksi ke Quran.com API v4 sedang tidak tersedia ({$errorReason}). Pastikan koneksi internet aktif atau jalankan perintah `php artisan quran:cache-pages`.",
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Built-in authentic Madinah Mushaf pages (Page 1: Al-Fatihah, Page 2: Al-Baqarah 1–5, Page 604: Al-Ikhlas, Al-Falaq, An-Nas)
     * so the application works out-of-the-box offline and in tests.
     */
    public function getBuiltInSeedPages(): array
    {
        return [
            1 => [
                [
                    'id' => 1,
                    'verse_key' => '1:1',
                    'chapter_id' => 1,
                    'verse_number' => 1,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ',
                    'translations' => [['text' => 'Dengan nama Allah Yang Maha Pengasih, Maha Penyayang.']],
                ],
                [
                    'id' => 2,
                    'verse_key' => '1:2',
                    'chapter_id' => 1,
                    'verse_number' => 2,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'ٱلْحَمْدُ لِلَّهِ رَبِّ ٱلْعَـٰلَمِينَ',
                    'translations' => [['text' => 'Segala puji bagi Allah, Tuhan seluruh alam,']],
                ],
                [
                    'id' => 3,
                    'verse_key' => '1:3',
                    'chapter_id' => 1,
                    'verse_number' => 3,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ',
                    'translations' => [['text' => 'Yang Maha Pengasih, Maha Penyayang,']],
                ],
                [
                    'id' => 4,
                    'verse_key' => '1:4',
                    'chapter_id' => 1,
                    'verse_number' => 4,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'مَـٰلِكِ يَوْمِ ٱلدِّينِ',
                    'translations' => [['text' => 'Pemilik hari pembalasan.']],
                ],
                [
                    'id' => 5,
                    'verse_key' => '1:5',
                    'chapter_id' => 1,
                    'verse_number' => 5,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ',
                    'translations' => [['text' => 'Hanya kepada Engkaulah kami menyembah dan hanya kepada Engkaulah kami mohon pertolongan.']],
                ],
                [
                    'id' => 6,
                    'verse_key' => '1:6',
                    'chapter_id' => 1,
                    'verse_number' => 6,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'ٱهْدِنَا ٱلصِّرَٰطَ ٱلْمُسْتَقِيمَ',
                    'translations' => [['text' => 'Tunjukilah kami jalan yang lurus,']],
                ],
                [
                    'id' => 7,
                    'verse_key' => '1:7',
                    'chapter_id' => 1,
                    'verse_number' => 7,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'صِرَٰطَ ٱلَّذِينَ أَنْعَمْتَ عَلَيْهِمْ غَيْرِ ٱلْمَغْضُوبِ عَلَيْهِمْ وَلَا ٱلضَّآلِّينَ',
                    'translations' => [['text' => '(yaitu) jalan orang-orang yang telah Engkau beri nikmat kepadanya; bukan (jalan) mereka yang dimurkai, dan bukan (pula jalan) mereka yang sesat.']],
                ],
            ],
            2 => [
                [
                    'id' => 8,
                    'verse_key' => '2:1',
                    'chapter_id' => 2,
                    'verse_number' => 1,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'الٓمٓ',
                    'translations' => [['text' => 'Alif Lam Mim.']],
                ],
                [
                    'id' => 9,
                    'verse_key' => '2:2',
                    'chapter_id' => 2,
                    'verse_number' => 2,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'ذَٰلِكَ ٱلْكِتَـٰبُ لَا رَيْبَ ۛ فِيهِ ۛ هُدًى لِّلْمُتَّقِينَ',
                    'translations' => [['text' => 'Kitab (Al-Qur\'an) ini tidak ada keraguan padanya; petunjuk bagi mereka yang bertakwa,']],
                ],
                [
                    'id' => 10,
                    'verse_key' => '2:3',
                    'chapter_id' => 2,
                    'verse_number' => 3,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'ٱلَّذِينَ يُؤْمِنُونَ بِٱلْغَيْبِ وَيُقِيمُونَ ٱلصَّلَوٰةَ وَمِمَّا رَزَقْنَـٰهُمْ يُنفِقُونَ',
                    'translations' => [['text' => '(yaitu) mereka yang beriman kepada yang gaib, melaksanakan salat, dan menginfakkan sebagian rezeki yang Kami berikan kepada mereka,']],
                ],
                [
                    'id' => 11,
                    'verse_key' => '2:4',
                    'chapter_id' => 2,
                    'verse_number' => 4,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'وَٱلَّذِينَ يُؤْمِنُونَ بِمَآ أُنزِلَ إِلَيْكَ وَمَآ أُنزِلَ مِن قَبْلِكَ وَبِٱلْـَٔاخِرَةِ هُمْ يُوقِنُونَ',
                    'translations' => [['text' => 'dan mereka yang beriman kepada (Al-Qur\'an) yang diturunkan kepadamu (Muhammad) dan (kitab-kitab) yang telah diturunkan sebelum engkau, dan mereka yakin akan adanya akhirat.']],
                ],
                [
                    'id' => 12,
                    'verse_key' => '2:5',
                    'chapter_id' => 2,
                    'verse_number' => 5,
                    'juz_number' => 1,
                    'hizb_number' => 1,
                    'text_uthmani' => 'أُو۟لَـٰٓئِكَ عَلَىٰ هُدًى مِّن رَّبِّهِمْ ۖ وَأُو۟لَـٰٓئِكَ هُمُ ٱلْمُفْلِحُونَ',
                    'translations' => [['text' => 'Merekalah yang mendapat petunjuk dari Tuhannya, dan mereka itulah orang-orang yang beruntung.']],
                ],
            ],
            45 => [
                [
                    'id' => 272,
                    'verse_key' => '2:265',
                    'chapter_id' => 2,
                    'verse_number' => 265,
                    'juz_number' => 3,
                    'hizb_number' => 5,
                    'text_uthmani' => 'وَمَثَلُ ٱلَّذِينَ يُنفِقُونَ أَمْوَٰلَهُمُ ٱبْتِغَآءَ مَرْضَاتِ ٱللَّهِ وَتَثْبِيتًا مِّنْ أَنفُسِهِمْ كَمَثَلِ جَنَّةٍۭ بِرَبْوَةٍ أَصَابَهَا وَابِلٌ فَـَٔاتَتْ أُكُلَهَا ضِعْفَيْنِ فَإِن لَّمْ يُصِبْهَا وَابِلٌ فَطَلٌّ ۗ وَٱللَّهُ بِمَا تَعْمَلُونَ بَصِيرٌ',
                    'translations' => [['text' => 'Dan perumpamaan orang yang menginfakkan hartanya untuk mencari rida Allah dan untuk memperteguh jiwa mereka, seperti sebuah kebun yang terletak di dataran tinggi yang disiram oleh hujan lebat, maka kebun itu menghasilkan buah-buahan dua kali lipat. Jika hujan lebat tidak menyiraminya, maka embun (pun memadai). Allah Maha Melihat apa yang kamu kerjakan.']],
                ],
                [
                    'id' => 273,
                    'verse_key' => '2:266',
                    'chapter_id' => 2,
                    'verse_number' => 266,
                    'juz_number' => 3,
                    'hizb_number' => 5,
                    'text_uthmani' => 'أَيَوَدُّ أَحَدُكُمْ أَن تَكُونَ لَهُۥ جَنَّةٌ مِّن نَّخِيلٍ وَأَعْنَابٍ تَجْرِى مِن تَحْتِهَا ٱلْأَنْهَـٰرُ لَهُۥ فِيهَا مِن كُلِّ ٱلثَّمَرَٰتِ وَأَصَابَهُ ٱلْكِبَرُ وَلَهُۥ ذُرِّيَّةٌ ضُعَفَآءُ فَأَصَابَهَآ إِعْصَارٌ فِيهِ نَارٌ فَٱحْتَرَقَتْ ۗ كَذَٰلِكَ يُبَيِّنُ ٱللَّهُ لَكُمُ ٱلْـَٔايَـٰتِ لَعَلَّكُمْ تَتَفَكَّرُونَ',
                    'translations' => [['text' => 'Apakah ada salah seorang di antara kamu yang ingin memiliki kebun kurma dan anggur yang mengalir di bawahnya sungai-sungai, di sana dia memiliki segala macam buah-buahan, kemudian datanglah masa tuanya sedang dia memiliki keturunan yang masih kecil-kecil. Lalu kebun itu ditiup angin keras yang mengandung api, sehingga terbakar. Demikianlah Allah menerangkan ayat-ayat-Nya kepadamu agar kamu memikirkannya.']],
                ],
                [
                    'id' => 274,
                    'verse_key' => '2:267',
                    'chapter_id' => 2,
                    'verse_number' => 267,
                    'juz_number' => 3,
                    'hizb_number' => 5,
                    'text_uthmani' => 'يَـٰٓأَيُّهَا ٱلَّذِينَ ءَامَنُوٓا۟ أَنفِقُوا۟ مِن طَيِّبَـٰتِ مَا كَسَبْتُمْ وَمِمَّآ أَخْرَجْنَا لَكُم مِّنَ ٱلْأَرْضِ ۖ وَلَا تَيَمَّمُوا۟ ٱلْخَبِيثَ مِنْهُ تُنفِقُونَ وَلَسْتُم بِـَٔاخِذِيهِ إِلَّآ أَن تُغْمِضُوا۟ فِيهِ ۚ وَٱعْلَمُوٓا۟ أَنَّ ٱللَّهَ غَنِىٌّ حَمِيدٌ',
                    'translations' => [['text' => 'Wahai orang-orang yang beriman! Infakkanlah sebagian dari hasil usahamu yang baik-baik dan sebagian dari apa yang Kami keluarkan dari bumi untukmu. Janganlah kamu memilih yang buruk untuk kamu keluarkan, padahal kamu sendiri tidak mau mengambilnya melainkan dengan memicingkan mata (enggan) terhadapnya. Dan ketahuilah bahwa Allah Mahakaya, Maha Terpuji.']],
                ],
                [
                    'id' => 275,
                    'verse_key' => '2:268',
                    'chapter_id' => 2,
                    'verse_number' => 268,
                    'juz_number' => 3,
                    'hizb_number' => 5,
                    'text_uthmani' => 'ٱلشَّيْطَـٰنُ يَعِدُكُمُ ٱلْفَقْرَ وَيَأْمُرُكُم بِٱلْفَحْشَآءِ ۖ وَٱللَّهُ يَعِدُكُم مَّغْفِرَةً مِّنْهُ وَفَضْلًا ۗ وَٱللَّهُ وَٰسِعٌ عَلِيمٌ',
                    'translations' => [['text' => 'Setan menjanjikan (menakut-nakuti) kemiskinan kepadamu dan menyuruh kamu berbuat keji (kikir), sedangkan Allah menjanjikan ampunan dan karunia-Nya kepadamu. Dan Allah Mahaluas, Maha Mengetahui.']],
                ],
                [
                    'id' => 276,
                    'verse_key' => '2:269',
                    'chapter_id' => 2,
                    'verse_number' => 269,
                    'juz_number' => 3,
                    'hizb_number' => 5,
                    'text_uthmani' => 'يُؤْتِى ٱلْحِكْمَةَ مَن يَشَآءُ ۚ وَمَن يُؤْتَ ٱلْحِكْمَةَ فَقَدْ أُوتِىَ خَيْرًا كَثِيرًا ۗ وَمَا يَذَّكَّرُ إِلَّآ أُو۟لُوا۟ ٱلْأَلْبَـٰبِ',
                    'translations' => [['text' => 'Dia memberikan hikmah kepada siapa yang Dia kehendaki. Barangsiapa diberi hikmah, sesungguhnya dia telah diberi kebaikan yang banyak. Dan tidak ada yang dapat mengambil pelajaran kecuali orang-orang yang mempunyai akal sehat.']],
                ],
            ],
            604 => [
                [
                    'id' => 6222,
                    'verse_key' => '112:1',
                    'chapter_id' => 112,
                    'verse_number' => 1,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'قُلْ هُوَ ٱللَّهُ أَحَدٌ',
                    'translations' => [['text' => 'Katakanlah (Muhammad), "Dialah Allah, Yang Maha Esa.']],
                ],
                [
                    'id' => 6223,
                    'verse_key' => '112:2',
                    'chapter_id' => 112,
                    'verse_number' => 2,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'ٱللَّهُ ٱلصَّمَدُ',
                    'translations' => [['text' => 'Allah tempat meminta segala sesuatu.']],
                ],
                [
                    'id' => 6224,
                    'verse_key' => '112:3',
                    'chapter_id' => 112,
                    'verse_number' => 3,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'لَمْ يَلِدْ وَلَمْ يُولَدْ',
                    'translations' => [['text' => '(Allah) tidak beranak dan tidak pula diperanakkan.']],
                ],
                [
                    'id' => 6225,
                    'verse_key' => '112:4',
                    'chapter_id' => 112,
                    'verse_number' => 4,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'وَلَمْ يَكُن لَّهُۥ كُفُوًا أَحَدٌۢ',
                    'translations' => [['text' => 'Dan tidak ada sesuatu yang setara dengan Dia."']],
                ],
                [
                    'id' => 6226,
                    'verse_key' => '113:1',
                    'chapter_id' => 113,
                    'verse_number' => 1,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'قُلْ أَعُوذُ بِرَبِّ ٱلْفَلَقِ',
                    'translations' => [['text' => 'Katakanlah, "Aku berlindung kepada Tuhan yang menguasai subuh (fajar),']],
                ],
                [
                    'id' => 6227,
                    'verse_key' => '113:2',
                    'chapter_id' => 113,
                    'verse_number' => 2,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'مِن شَرِّ مَا خَلَقَ',
                    'translations' => [['text' => 'dari kejahatan (makhluk yang) Dia ciptakan,']],
                ],
                [
                    'id' => 6228,
                    'verse_key' => '113:3',
                    'chapter_id' => 113,
                    'verse_number' => 3,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'وَمِن شَرِّ غَاسِقٍ إِذَا وَقَبَ',
                    'translations' => [['text' => 'dan dari kejahatan malam apabila telah gelap gulita,']],
                ],
                [
                    'id' => 6229,
                    'verse_key' => '113:4',
                    'chapter_id' => 113,
                    'verse_number' => 4,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'وَمِن شَرِّ ٱلنَّفَّـٰثَـٰتِ فِى ٱلْعُقَدِ',
                    'translations' => [['text' => 'dan dari kejahatan (perempuan-perempuan) penyihir yang meniup pada buhul-buhul (talinya),']],
                ],
                [
                    'id' => 6230,
                    'verse_key' => '113:5',
                    'chapter_id' => 113,
                    'verse_number' => 5,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'وَمِن شَرِّ حَاسِدٍ إِذَا حَسَدَ',
                    'translations' => [['text' => 'dan dari kejahatan orang yang dengki apabila dia dengki."']],
                ],
                [
                    'id' => 6231,
                    'verse_key' => '114:1',
                    'chapter_id' => 114,
                    'verse_number' => 1,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'قُلْ أَعُوذُ بِرَبِّ ٱلنَّاسِ',
                    'translations' => [['text' => 'Katakanlah, "Aku berlindung kepada Tuhannya manusia,']],
                ],
                [
                    'id' => 6232,
                    'verse_key' => '114:2',
                    'chapter_id' => 114,
                    'verse_number' => 2,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'مَلِكِ ٱلنَّاسِ',
                    'translations' => [['text' => 'Raja manusia,']],
                ],
                [
                    'id' => 6233,
                    'verse_key' => '114:3',
                    'chapter_id' => 114,
                    'verse_number' => 3,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'إِلَـٰهِ ٱلنَّاسِ',
                    'translations' => [['text' => 'sembahan manusia,']],
                ],
                [
                    'id' => 6234,
                    'verse_key' => '114:4',
                    'chapter_id' => 114,
                    'verse_number' => 4,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'مِن شَرِّ ٱلْوَسْوَاسِ ٱلْخَنَّاسِ',
                    'translations' => [['text' => 'dari kejahatan (bisikan) setan yang bersembunyi,']],
                ],
                [
                    'id' => 6235,
                    'verse_key' => '114:5',
                    'chapter_id' => 114,
                    'verse_number' => 5,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'ٱلَّذِى يُوَسْوِسُ فِى صُدُورِ ٱلنَّاسِ',
                    'translations' => [['text' => 'yang membisikkan (kejahatan) ke dalam dada manusia,']],
                ],
                [
                    'id' => 6236,
                    'verse_key' => '114:6',
                    'chapter_id' => 114,
                    'verse_number' => 6,
                    'juz_number' => 30,
                    'hizb_number' => 60,
                    'text_uthmani' => 'مِنَ ٱلْجِنَّةِ وَٱلنَّاسِ',
                    'translations' => [['text' => 'dari (golongan) jin dan manusia."']],
                ],
            ],
        ];
    }

    /**
     * Store normalized payload in Laravel Cache safely.
     */
    private function putInAppCache(string $cacheKey, array $payload): void
    {
        try {
            Cache::put($cacheKey, $payload, now()->addDays(90));
        } catch (Throwable) {
            // Ignore if cache driver is unavailable
        }
    }
}

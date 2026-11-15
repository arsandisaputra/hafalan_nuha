<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Core Domain Helper for the 15-Line Madinah Mushaf (604 Pages, 30 Juz).
 *
 * Handles:
 * - Page validation (1..604)
 * - Standard Madinah Mushaf Juz calculation (Pages 1–21 = Juz 1, Pages 22–41 = Juz 2, ..., Pages 582–604 = Juz 30)
 * - Page position within Juz (e.g., Page 45 -> "Halaman 4 dari 20 di Juz 3")
 * - Complete 114 Surah metadata lookup (Latin name, Arabic script, Indonesian meaning)
 */
class MushafPageHelper
{
    public const MIN_PAGE = 1;
    public const MAX_PAGE = 604;
    public const TOTAL_JUZ = 30;

    /**
     * Exact starting page for each Juz in the standard 15-line Madinah Mushaf.
     *
     * @var array<int, int>
     */
    public const JUZ_START_PAGES = [
        1 => 1,
        2 => 22,
        3 => 42,
        4 => 62,
        5 => 82,
        6 => 102,
        7 => 122,
        8 => 142,
        9 => 162,
        10 => 182,
        11 => 202,
        12 => 222,
        13 => 242,
        14 => 262,
        15 => 282,
        16 => 302,
        17 => 322,
        18 => 342,
        19 => 362,
        20 => 382,
        21 => 402,
        22 => 422,
        23 => 442,
        24 => 462,
        25 => 482,
        26 => 502,
        27 => 522,
        28 => 542,
        29 => 562,
        30 => 582,
    ];

    /**
     * Complete 114 Surahs metadata (Latin name, Arabic name, Indonesian translation).
     *
     * @var array<int, array{name_simple: string, name_arabic: string, name_id: string}>
     */
    public const SURAH_META = [
        1 => ['name_simple' => 'Al-Fatihah', 'name_arabic' => 'الفاتحة', 'name_id' => 'Pembukaan'],
        2 => ['name_simple' => 'Al-Baqarah', 'name_arabic' => 'البقرة', 'name_id' => 'Sapi Betina'],
        3 => ['name_simple' => "Ali 'Imran", 'name_arabic' => 'آل عمران', 'name_id' => 'Keluarga Imran'],
        4 => ['name_simple' => "An-Nisa'", 'name_arabic' => 'النساء', 'name_id' => 'Wanita'],
        5 => ['name_simple' => "Al-Ma'idah", 'name_arabic' => 'المائدة', 'name_id' => 'Hidangan'],
        6 => ['name_simple' => "Al-An'am", 'name_arabic' => 'الأنعام', 'name_id' => 'Binatang Ternak'],
        7 => ['name_simple' => "Al-A'raf", 'name_arabic' => 'الأعراف', 'name_id' => 'Tempat Tertinggi'],
        8 => ['name_simple' => 'Al-Anfal', 'name_arabic' => 'الأنفال', 'name_id' => 'Harta Rampasan Perang'],
        9 => ['name_simple' => 'At-Taubah', 'name_arabic' => 'التوبة', 'name_id' => 'Pengampunan'],
        10 => ['name_simple' => 'Yunus', 'name_arabic' => 'يونس', 'name_id' => 'Yunus'],
        11 => ['name_simple' => 'Hud', 'name_arabic' => 'هود', 'name_id' => 'Hud'],
        12 => ['name_simple' => 'Yusuf', 'name_arabic' => 'يوسف', 'name_id' => 'Yusuf'],
        13 => ['name_simple' => "Ar-Ra'd", 'name_arabic' => 'الرعد', 'name_id' => 'Guruh'],
        14 => ['name_simple' => 'Ibrahim', 'name_arabic' => 'إبراهيم', 'name_id' => 'Ibrahim'],
        15 => ['name_simple' => 'Al-Hijr', 'name_arabic' => 'الحجر', 'name_id' => 'Hijr'],
        16 => ['name_simple' => 'An-Nahl', 'name_arabic' => 'النحل', 'name_id' => 'Lebah'],
        17 => ['name_simple' => "Al-Isra'", 'name_arabic' => 'الإسراء', 'name_id' => 'Perjalanan Malam'],
        18 => ['name_simple' => 'Al-Kahf', 'name_arabic' => 'الكهف', 'name_id' => 'Gua'],
        19 => ['name_simple' => 'Maryam', 'name_arabic' => 'مريم', 'name_id' => 'Maryam'],
        20 => ['name_simple' => 'Taha', 'name_arabic' => 'طه', 'name_id' => 'Taha'],
        21 => ['name_simple' => "Al-Anbiya'", 'name_arabic' => 'الأنبياء', 'name_id' => 'Para Nabi'],
        22 => ['name_simple' => 'Al-Hajj', 'name_arabic' => 'الحج', 'name_id' => 'Haji'],
        23 => ['name_simple' => "Al-Mu'minun", 'name_arabic' => 'المؤمنون', 'name_id' => 'Orang-Orang Mukmin'],
        24 => ['name_simple' => 'An-Nur', 'name_arabic' => 'النور', 'name_id' => 'Cahaya'],
        25 => ['name_simple' => 'Al-Furqan', 'name_arabic' => 'الفرقان', 'name_id' => 'Pembeda'],
        26 => ['name_simple' => "Asy-Syu'ara'", 'name_arabic' => 'الشعراء', 'name_id' => 'Para Penyair'],
        27 => ['name_simple' => 'An-Naml', 'name_arabic' => 'النمل', 'name_id' => 'Semut'],
        28 => ['name_simple' => 'Al-Qasas', 'name_arabic' => 'القصص', 'name_id' => 'Kisah-Kisah'],
        29 => ['name_simple' => "Al-'Ankabut", 'name_arabic' => 'العنكبوت', 'name_id' => 'Laba-Laba'],
        30 => ['name_simple' => 'Ar-Rum', 'name_arabic' => 'الروم', 'name_id' => 'Bangsa Romawi'],
        31 => ['name_simple' => 'Luqman', 'name_arabic' => 'لقمان', 'name_id' => 'Luqman'],
        32 => ['name_simple' => 'As-Sajdah', 'name_arabic' => 'السجدة', 'name_id' => 'Sajdah'],
        33 => ['name_simple' => 'Al-Ahzab', 'name_arabic' => 'الأحزاب', 'name_id' => 'Golongan yang Bersekutu'],
        34 => ['name_simple' => "Saba'", 'name_arabic' => 'سبأ', 'name_id' => "Saba'"],
        35 => ['name_simple' => 'Fatir', 'name_arabic' => 'فاطر', 'name_id' => 'Pencipta'],
        36 => ['name_simple' => 'Yasin', 'name_arabic' => 'يس', 'name_id' => 'Yasin'],
        37 => ['name_simple' => 'As-Saffat', 'name_arabic' => 'الصافات', 'name_id' => 'Barisan-Barisan'],
        38 => ['name_simple' => 'Sad', 'name_arabic' => 'ص', 'name_id' => 'Sad'],
        39 => ['name_simple' => 'Az-Zumar', 'name_arabic' => 'الزمر', 'name_id' => 'Rombongan'],
        40 => ['name_simple' => 'Ghafir', 'name_arabic' => 'غافر', 'name_id' => 'Maha Pengampun'],
        41 => ['name_simple' => 'Fussilat', 'name_arabic' => 'فصلت', 'name_id' => 'Yang Dijelaskan'],
        42 => ['name_simple' => 'Asy-Syura', 'name_arabic' => 'الشورى', 'name_id' => 'Musyawarah'],
        43 => ['name_simple' => 'Az-Zukhruf', 'name_arabic' => 'الزخرف', 'name_id' => 'Perhiasan'],
        44 => ['name_simple' => 'Ad-Dukhan', 'name_arabic' => 'الدخان', 'name_id' => 'Kabut'],
        45 => ['name_simple' => 'Al-Jasiyah', 'name_arabic' => 'الجاثية', 'name_id' => 'Yang Berlutut'],
        46 => ['name_simple' => 'Al-Ahqaf', 'name_arabic' => 'الأحقاف', 'name_id' => 'Bukit Pasir'],
        47 => ['name_simple' => 'Muhammad', 'name_arabic' => 'محمد', 'name_id' => 'Muhammad'],
        48 => ['name_simple' => 'Al-Fath', 'name_arabic' => 'الفتح', 'name_id' => 'Kemenangan'],
        49 => ['name_simple' => 'Al-Hujurat', 'name_arabic' => 'الحجرات', 'name_id' => 'Kamar-Kamar'],
        50 => ['name_simple' => 'Qaf', 'name_arabic' => 'ق', 'name_id' => 'Qaf'],
        51 => ['name_simple' => 'Az-Zariyat', 'name_arabic' => 'الذاريات', 'name_id' => 'Angin yang Menerbangkan'],
        52 => ['name_simple' => 'At-Tur', 'name_arabic' => 'الطور', 'name_id' => 'Bukit Tursina'],
        53 => ['name_simple' => 'An-Najm', 'name_arabic' => 'النجم', 'name_id' => 'Bintang'],
        54 => ['name_simple' => 'Al-Qamar', 'name_arabic' => 'القمر', 'name_id' => 'Bulan'],
        55 => ['name_simple' => 'Ar-Rahman', 'name_arabic' => 'الرحمن', 'name_id' => 'Yang Maha Pengasih'],
        56 => ['name_simple' => "Al-Waqi'ah", 'name_arabic' => 'الواقعة', 'name_id' => 'Hari Kiamat'],
        57 => ['name_simple' => 'Al-Hadid', 'name_arabic' => 'الحديد', 'name_id' => 'Besi'],
        58 => ['name_simple' => 'Al-Mujadalah', 'name_arabic' => 'المجادلة', 'name_id' => 'Gugatan'],
        59 => ['name_simple' => 'Al-Hasyr', 'name_arabic' => 'الحشر', 'name_id' => 'Pengusiran'],
        60 => ['name_simple' => 'Al-Mumtahanah', 'name_arabic' => 'الممتحنة', 'name_id' => 'Wanita yang Diuji'],
        61 => ['name_simple' => 'As-Saff', 'name_arabic' => 'الصف', 'name_id' => 'Barisan'],
        62 => ['name_simple' => "Al-Jumu'ah", 'name_arabic' => 'الجمعة', 'name_id' => 'Jumat'],
        63 => ['name_simple' => 'Al-Munafiqun', 'name_arabic' => 'المنافقون', 'name_id' => 'Orang-Orang Munafik'],
        64 => ['name_simple' => 'At-Taghabun', 'name_arabic' => 'التغابن', 'name_id' => 'Pengungkapan Kesalahan'],
        65 => ['name_simple' => 'At-Talaq', 'name_arabic' => 'الطلاق', 'name_id' => 'Talak'],
        66 => ['name_simple' => 'At-Tahrim', 'name_arabic' => 'التحريم', 'name_id' => 'Pengharaman'],
        67 => ['name_simple' => 'Al-Mulk', 'name_arabic' => 'الملك', 'name_id' => 'Kerajaan'],
        68 => ['name_simple' => 'Al-Qalam', 'name_arabic' => 'القلم', 'name_id' => 'Pena'],
        69 => ['name_simple' => 'Al-Haqqah', 'name_arabic' => 'الحاقة', 'name_id' => 'Hari Kiamat'],
        70 => ['name_simple' => "Al-Ma'arij", 'name_arabic' => 'المعارج', 'name_id' => 'Tempat Naik'],
        71 => ['name_simple' => 'Nuh', 'name_arabic' => 'نوح', 'name_id' => 'Nuh'],
        72 => ['name_simple' => 'Al-Jinn', 'name_arabic' => ' الجن', 'name_id' => 'Jin'],
        73 => ['name_simple' => 'Al-Muzzammil', 'name_arabic' => 'المزمل', 'name_id' => 'Orang yang Berselimut'],
        74 => ['name_simple' => 'Al-Muddassir', 'name_arabic' => 'المدثر', 'name_id' => 'Orang yang Berkemul'],
        75 => ['name_simple' => 'Al-Qiyamah', 'name_arabic' => 'القيامة', 'name_id' => 'Hari Kiamat'],
        76 => ['name_simple' => 'Al-Insan', 'name_arabic' => 'الإنسان', 'name_id' => 'Manusia'],
        77 => ['name_simple' => 'Al-Mursalat', 'name_arabic' => 'المرسلات', 'name_id' => 'Malaikat yang Diutus'],
        78 => ['name_simple' => "An-Naba'", 'name_arabic' => 'النبأ', 'name_id' => 'Berita Besar'],
        79 => ['name_simple' => "An-Nazi'at", 'name_arabic' => 'النازعات', 'name_id' => 'Malaikat yang Mencabut'],
        80 => ['name_simple' => "'Abasa", 'name_arabic' => 'عبس', 'name_id' => 'Bermuka Masam'],
        81 => ['name_simple' => 'At-Takwir', 'name_arabic' => 'التكوير', 'name_id' => 'Penggulungan'],
        82 => ['name_simple' => 'Al-Infitar', 'name_arabic' => 'الانفطار', 'name_id' => 'Terbelah'],
        83 => ['name_simple' => 'Al-Mutaffifin', 'name_arabic' => 'المطففين', 'name_id' => 'Orang-Orang yang Curang'],
        84 => ['name_simple' => 'Al-Insyiqaq', 'name_arabic' => 'الانشقاق', 'name_id' => 'Terbelah'],
        85 => ['name_simple' => 'Al-Buruj', 'name_arabic' => 'البروج', 'name_id' => 'Gugusan Bintang'],
        86 => ['name_simple' => 'At-Tariq', 'name_arabic' => 'الطارق', 'name_id' => 'Yang Datang di Malam Hari'],
        87 => ['name_simple' => "Al-A'la", 'name_arabic' => 'الأعلى', 'name_id' => 'Yang Paling Tinggi'],
        88 => ['name_simple' => 'Al-Ghasyiyah', 'name_arabic' => 'الغاشية', 'name_id' => 'Hari Pembalasan'],
        89 => ['name_simple' => 'Al-Fajr', 'name_arabic' => 'الفجر', 'name_id' => 'Fajar'],
        90 => ['name_simple' => 'Al-Balad', 'name_arabic' => 'البلد', 'name_id' => 'Negeri'],
        91 => ['name_simple' => 'Asy-Syams', 'name_arabic' => 'الشمس', 'name_id' => 'Matahari'],
        92 => ['name_simple' => 'Al-Lail', 'name_arabic' => 'الليل', 'name_id' => 'Malam'],
        93 => ['name_simple' => 'Ad-Duha', 'name_arabic' => 'الضحى', 'name_id' => 'Duha'],
        94 => ['name_simple' => 'Asy-Syarh', 'name_arabic' => 'الشرح', 'name_id' => 'Kelapangan'],
        95 => ['name_simple' => 'At-Tin', 'name_arabic' => 'التين', 'name_id' => 'Buah Tin'],
        96 => ['name_simple' => "Al-'Alaq", 'name_arabic' => 'العلق', 'name_id' => 'Segumpal Darah'],
        97 => ['name_simple' => 'Al-Qadr', 'name_arabic' => 'القدر', 'name_id' => 'Kemuliaan'],
        98 => ['name_simple' => 'Al-Bayyinah', 'name_arabic' => 'البينة', 'name_id' => 'Bukti Nyata'],
        99 => ['name_simple' => 'Az-Zalzalah', 'name_arabic' => 'الزلزلة', 'name_id' => 'Kegoncangan'],
        100 => ['name_simple' => "Al-'Adiyat", 'name_arabic' => 'العاديات', 'name_id' => 'Kuda Perang yang Berlari Kencang'],
        101 => ['name_simple' => "Al-Qari'ah", 'name_arabic' => 'القارعة', 'name_id' => 'Hari Kiamat'],
        102 => ['name_simple' => 'At-Takasur', 'name_arabic' => 'التكاثر', 'name_id' => 'Bermegah-Megahan'],
        103 => ['name_simple' => "Al-'Asr", 'name_arabic' => 'العصر', 'name_id' => 'Masa'],
        104 => ['name_simple' => 'Al-Humazah', 'name_arabic' => 'الهمزة', 'name_id' => 'Pengumpat'],
        105 => ['name_simple' => 'Al-Fil', 'name_arabic' => 'الفيل', 'name_id' => 'Gajah'],
        106 => ['name_simple' => 'Quraisy', 'name_arabic' => 'قريش', 'name_id' => 'Suku Quraisy'],
        107 => ['name_simple' => "Al-Ma'un", 'name_arabic' => 'الماعون', 'name_id' => 'Barang yang Berguna'],
        108 => ['name_simple' => 'Al-Kausar', 'name_arabic' => 'الكوثر', 'name_id' => 'Nikmat yang Banyak'],
        109 => ['name_simple' => 'Al-Kafirun', 'name_arabic' => 'الكافرون', 'name_id' => 'Orang-Orang Kafir'],
        110 => ['name_simple' => 'An-Nasr', 'name_arabic' => 'النصر', 'name_id' => 'Pertolongan'],
        111 => ['name_simple' => 'Al-Lahab', 'name_arabic' => 'المسد', 'name_id' => 'Gejolak Api'],
        112 => ['name_simple' => 'Al-Ikhlas', 'name_arabic' => 'الإخلاص', 'name_id' => 'Ikhlas'],
        113 => ['name_simple' => 'Al-Falaq', 'name_arabic' => 'الفلق', 'name_id' => 'Waktu Subuh'],
        114 => ['name_simple' => 'An-Nas', 'name_arabic' => 'الناس', 'name_id' => 'Manusia'],
    ];

    /**
     * Validate and clamp/assert Mushaf page number (1 to 604).
     */
    public static function validatePageNumber(int $pageNumber): int
    {
        if ($pageNumber < self::MIN_PAGE || $pageNumber > self::MAX_PAGE) {
            throw new InvalidArgumentException("Nomor halaman Mushaf harus antara 1 sampai 604 (diterima: {$pageNumber}).");
        }

        return $pageNumber;
    }

    /**
     * Calculate Juz number (1–30) from Mushaf page number (1–604)
     * following the 15-line Madinah Mushaf standard (Pages 1–21 = Juz 1, 22–41 = Juz 2, ..., 582–604 = Juz 30).
     */
    public static function calculateJuzFromPage(int $pageNumber): int
    {
        self::validatePageNumber($pageNumber);

        for ($juz = self::TOTAL_JUZ; $juz >= 1; $juz--) {
            if ($pageNumber >= self::JUZ_START_PAGES[$juz]) {
                return $juz;
            }
        }

        return 1;
    }

    /**
     * Get the start and end page numbers for a given Juz (1–30).
     *
     * @return array{juz_number: int, start_page: int, end_page: int, total_pages: int}
     */
    public static function getJuzPageRange(int $juzNumber): array
    {
        $juzNumber = max(1, min(self::TOTAL_JUZ, $juzNumber));
        $startPage = self::JUZ_START_PAGES[$juzNumber];
        $endPage = $juzNumber < self::TOTAL_JUZ
            ? self::JUZ_START_PAGES[$juzNumber + 1] - 1
            : self::MAX_PAGE;

        return [
            'juz_number' => $juzNumber,
            'start_page' => $startPage,
            'end_page' => $endPage,
            'total_pages' => ($endPage - $startPage) + 1,
        ];
    }

    /**
     * Compute rich page header metadata for a given Mushaf page (1–604).
     * Example for Page 45:
     * - juz_number: 3
     * - page_in_juz: 4
     * - total_pages_in_juz: 20
     * - page_label: "Halaman 45 / 604"
     * - juz_position_label: "Halaman 4 dari 20 di Juz 3"
     */
    public static function getPagePositionMetadata(int $pageNumber): array
    {
        self::validatePageNumber($pageNumber);

        $juzNumber = self::calculateJuzFromPage($pageNumber);
        $range = self::getJuzPageRange($juzNumber);
        $pageInJuz = ($pageNumber - $range['start_page']) + 1;
        $totalPagesInJuz = $range['total_pages'];

        return [
            'page_number' => $pageNumber,
            'juz_number' => $juzNumber,
            'page_in_juz' => $pageInJuz,
            'total_pages_in_juz' => $totalPagesInJuz,
            'juz_start_page' => $range['start_page'],
            'juz_end_page' => $range['end_page'],
            'page_label' => "Halaman {$pageNumber} / " . self::MAX_PAGE,
            'juz_position_label' => "Halaman {$pageInJuz} dari {$totalPagesInJuz} di Juz {$juzNumber}",
            'prev_page' => $pageNumber > self::MIN_PAGE ? $pageNumber - 1 : null,
            'next_page' => $pageNumber < self::MAX_PAGE ? $pageNumber + 1 : null,
        ];
    }

    /**
     * Retrieve Surah metadata by chapter ID (1–114).
     *
     * @return array{id: int, name_simple: string, name_arabic: string, name_id: string}
     */
    public static function getSurahMeta(int $chapterId): array
    {
        $meta = self::SURAH_META[$chapterId] ?? [
            'name_simple' => "Surah {$chapterId}",
            'name_arabic' => '',
            'name_id' => '',
        ];

        return array_merge(['id' => $chapterId], $meta);
    }

    /**
     * Split an Arabic Uthmani verse into:
     * - `first_words`: first $anchorWordCount words (for Anchor Words Highlight & Mode Pancingan Awal)
     * - `remaining_words`: the rest of the verse text (blurred in Mode Pancingan Awal)
     *
     * Preserves combining marks, waqf signs, and Uthmani ligatures cleanly.
     *
     * @return array{first_words: string, remaining_words: string, total_words: int}
     */
    public static function splitAnchorWords(string $arabicText, int $anchorWordCount = 3): array
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $arabicText) ?? '');
        if ($trimmed === '') {
            return [
                'first_words' => '',
                'remaining_words' => '',
                'total_words' => 0,
            ];
        }

        $words = explode(' ', $trimmed);
        $totalWords = count($words);

        // If a verse is very short (<= 3 words), keep at least 1-2 words as anchor and 1 word in remaining if possible
        $takeCount = $totalWords <= 3
            ? max(1, min(2, $totalWords - 1))
            : min($anchorWordCount, $totalWords);

        $firstWords = implode(' ', array_slice($words, 0, $takeCount));
        $remainingWords = implode(' ', array_slice($words, $takeCount));

        return [
            'first_words' => $firstWords,
            'remaining_words' => $remainingWords,
            'total_words' => $totalWords,
        ];
    }
}

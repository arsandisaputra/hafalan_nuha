<?php

namespace App\Repositories;

use App\Models\PageNote;
use App\Models\PageProgress;
use App\Models\QuranPageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class QuranPageRepository
{
    /**
     * Find a cached Mushaf page record from the database.
     */
    public function findCachedPage(int $pageNumber): ?QuranPageCache
    {
        return QuranPageCache::query()->find($pageNumber);
    }

    /**
     * Persist or update a normalized Mushaf page payload in the database cache.
     */
    public function saveCachedPage(int $pageNumber, int $juzNumber, string $surahNames, array $payload): QuranPageCache
    {
        return QuranPageCache::query()->updateOrCreate(
            ['page_number' => $pageNumber],
            [
                'juz_number' => $juzNumber,
                'surah_names' => $surahNames,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Get or initialize progress record for a specific page.
     */
    public function getPageProgress(int $pageNumber): PageProgress
    {
        return PageProgress::query()->firstOrNew(
            ['page_number' => $pageNumber],
            [
                'status' => PageProgress::STATUS_BELUM,
                'review_count' => 0,
                'last_reviewed_at' => null,
            ]
        );
    }

    /**
     * Update memorization status ('belum', 'proses', 'hafal') for a page.
     */
    public function updateStatus(int $pageNumber, string $status): PageProgress
    {
        $progress = PageProgress::query()->firstOrNew(['page_number' => $pageNumber]);
        $progress->status = $status;

        if ($status === PageProgress::STATUS_HAFAL && $progress->last_reviewed_at === null) {
            $progress->last_reviewed_at = Carbon::now();
            $progress->review_count = max(1, (int) $progress->review_count);
        }

        $progress->save();

        return $progress;
    }

    /**
     * Log today's Muraja'ah for a page (increments review_count and updates last_reviewed_at).
     */
    public function incrementMurajaah(int $pageNumber): PageProgress
    {
        $progress = PageProgress::query()->firstOrNew(
            ['page_number' => $pageNumber],
            [
                'status' => PageProgress::STATUS_PROSES,
                'review_count' => 0,
            ]
        );

        if ($progress->status === PageProgress::STATUS_BELUM) {
            $progress->status = PageProgress::STATUS_PROSES;
        }

        $progress->review_count = ((int) $progress->review_count) + 1;
        $progress->last_reviewed_at = Carbon::now();
        $progress->save();

        return $progress;
    }

    /**
     * Retrieve all PageProgress rows keyed by page_number.
     *
     * @return Collection<int, PageProgress>
     */
    public function getAllProgressKeyedByPage(): Collection
    {
        return PageProgress::query()
            ->get()
            ->keyBy('page_number');
    }

    /**
     * Get or initialize personal Mind-Link / Benang Merah notes for a page.
     */
    public function getPageNote(int $pageNumber): PageNote
    {
        return PageNote::query()->firstOrNew(
            ['page_number' => $pageNumber],
            [
                'narrative_summary' => '',
                'verse_links_json' => [],
            ]
        );
    }

    /**
     * Upsert Mind-Link / Benang Merah notes and per-verse cues for a page.
     */
    public function savePageNote(int $pageNumber, ?string $narrativeSummary, ?array $verseLinks = null): PageNote
    {
        $existing = PageNote::query()->where('page_number', $pageNumber)->first();

        $attributes = [
            'narrative_summary' => $narrativeSummary,
        ];

        if ($verseLinks !== null) {
            $attributes['verse_links_json'] = $verseLinks;
        } elseif ($existing === null) {
            $attributes['verse_links_json'] = [];
        }

        return PageNote::query()->updateOrCreate(
            ['page_number' => $pageNumber],
            $attributes
        );
    }

    /**
     * Get page numbers that have saved notes.
     *
     * @return array<int, bool>
     */
    public function getPagesWithNotesMap(): array
    {
        return PageNote::query()
            ->whereNotNull('narrative_summary')
            ->where('narrative_summary', '!=', '')
            ->pluck('page_number')
            ->mapWithKeys(fn ($page) => [(int) $page => true])
            ->all();
    }
}

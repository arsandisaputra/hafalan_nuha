<?php

namespace App\Services;

use App\Models\PageNote;
use App\Models\PageProgress;
use App\Repositories\QuranPageRepository;
use App\Support\MushafPageHelper;
use Illuminate\Support\Carbon;

class TahfidzProgressService
{
    public function __construct(
        private readonly QuranPageRepository $repository
    ) {}

    /**
     * Get progress and notes for a single Mushaf page.
     *
     * @return array{progress: PageProgress, note: PageNote}
     */
    public function getPageUserState(int $pageNumber): array
    {
        $pageNumber = MushafPageHelper::validatePageNumber($pageNumber);

        return [
            'progress' => $this->repository->getPageProgress($pageNumber),
            'note' => $this->repository->getPageNote($pageNumber),
        ];
    }

    /**
     * Update page memorization status ('belum', 'proses', 'hafal').
     */
    public function updatePageStatus(int $pageNumber, string $status): PageProgress
    {
        $pageNumber = MushafPageHelper::validatePageNumber($pageNumber);

        return $this->repository->updateStatus($pageNumber, $status);
    }

    /**
     * Record a Muraja'ah session for today.
     */
    public function logPageMurajaah(int $pageNumber): PageProgress
    {
        $pageNumber = MushafPageHelper::validatePageNumber($pageNumber);

        return $this->repository->incrementMurajaah($pageNumber);
    }

    /**
     * Save Mind-Link / Benang Merah narrative summary and per-verse anchor cues.
     */
    public function savePageNote(int $pageNumber, ?string $narrativeSummary, ?array $verseLinks = null): PageNote
    {
        $pageNumber = MushafPageHelper::validatePageNumber($pageNumber);

        return $this->repository->savePageNote($pageNumber, $narrativeSummary, $verseLinks);
    }

    /**
     * Build the 30-Juz x ~20-Page Dashboard Matrix and overall Tahfidz/Muraja'ah summary metrics.
     */
    public function buildThirtyJuzDashboard(): array
    {
        $progressByPage = $this->repository->getAllProgressKeyedByPage();
        $pagesWithNotes = $this->repository->getPagesWithNotesMap();
        $staleThreshold = Carbon::now()->subDays(7);

        $totalHafal = 0;
        $totalProses = 0;
        $totalBelum = 0;
        $totalNeedsMurajaah = 0;
        $totalReviews = 0;
        $stalePagesList = [];
        $juzGrid = [];

        for ($juz = 1; $juz <= MushafPageHelper::TOTAL_JUZ; $juz++) {
            $range = MushafPageHelper::getJuzPageRange($juz);
            $pagesInJuz = [];
            $juzHafalCount = 0;
            $juzProsesCount = 0;
            $juzStaleCount = 0;

            for ($page = $range['start_page']; $page <= $range['end_page']; $page++) {
                /** @var PageProgress|null $record */
                $record = $progressByPage->get($page);
                $status = $record ? $record->status : PageProgress::STATUS_BELUM;
                $reviewCount = $record ? (int) $record->review_count : 0;
                $lastReviewedAt = $record?->last_reviewed_at;

                $needsMurajaah = $status !== PageProgress::STATUS_BELUM
                    && ($lastReviewedAt === null || $lastReviewedAt->lt($staleThreshold));

                $daysSinceReview = $lastReviewedAt
                    ? (int) floor($lastReviewedAt->diffInDays(Carbon::now()))
                    : null;

                if ($status === PageProgress::STATUS_HAFAL) {
                    $totalHafal++;
                    $juzHafalCount++;
                } elseif ($status === PageProgress::STATUS_PROSES) {
                    $totalProses++;
                    $juzProsesCount++;
                } else {
                    $totalBelum++;
                }

                if ($needsMurajaah) {
                    $totalNeedsMurajaah++;
                    $juzStaleCount++;
                    $stalePagesList[] = [
                        'page_number' => $page,
                        'juz_number' => $juz,
                        'status' => $status,
                        'review_count' => $reviewCount,
                        'last_reviewed_human' => $lastReviewedAt ? $lastReviewedAt->diffForHumans() : 'Belum pernah muraja\'ah',
                        'days_since_review' => $daysSinceReview,
                    ];
                }

                $totalReviews += $reviewCount;

                $pagesInJuz[] = [
                    'page_number' => $page,
                    'page_in_juz' => ($page - $range['start_page']) + 1,
                    'status' => $status,
                    'review_count' => $reviewCount,
                    'last_reviewed_at' => $lastReviewedAt?->toIso8601String(),
                    'last_reviewed_human' => $lastReviewedAt ? $lastReviewedAt->diffForHumans() : null,
                    'days_since_review' => $daysSinceReview,
                    'needs_murajaah' => $needsMurajaah,
                    'has_notes' => isset($pagesWithNotes[$page]),
                ];
            }

            $juzCompletionPercent = (int) round(($juzHafalCount / $range['total_pages']) * 100);

            $juzGrid[] = [
                'juz_number' => $juz,
                'start_page' => $range['start_page'],
                'end_page' => $range['end_page'],
                'total_pages' => $range['total_pages'],
                'hafal_count' => $juzHafalCount,
                'proses_count' => $juzProsesCount,
                'stale_count' => $juzStaleCount,
                'completion_percent' => $juzCompletionPercent,
                'pages' => $pagesInJuz,
            ];
        }

        return [
            'summary' => [
                'total_pages' => MushafPageHelper::MAX_PAGE,
                'hafal_count' => $totalHafal,
                'proses_count' => $totalProses,
                'belum_count' => $totalBelum,
                'hafal_percent' => round(($totalHafal / MushafPageHelper::MAX_PAGE) * 100, 1),
                'needs_murajaah_count' => $totalNeedsMurajaah,
                'total_reviews' => $totalReviews,
                'notes_count' => count($pagesWithNotes),
            ],
            'stale_pages' => array_slice($stalePagesList, 0, 18),
            'juz_grid' => $juzGrid,
        ];
    }
}

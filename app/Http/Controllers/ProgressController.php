<?php

namespace App\Http\Controllers;

use App\Models\PageProgress;
use App\Services\TahfidzProgressService;
use App\Support\MushafPageHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __construct(
        private readonly TahfidzProgressService $progressService
    ) {}

    /**
     * Render the 30-Juz x 20-Page Progress & Muraja'ah Overview Grid.
     */
    public function index(): View
    {
        $dashboard = $this->progressService->buildThirtyJuzDashboard();

        return view('mushaf.dashboard', [
            'summary' => $dashboard['summary'],
            'stalePages' => $dashboard['stale_pages'],
            'juzGrid' => $dashboard['juz_grid'],
        ]);
    }

    /**
     * Update the memorization status ('belum', 'proses', 'hafal') for a given Mushaf page.
     */
    public function updateStatus(Request $request, int $pageNumber): JsonResponse
    {
        if ($pageNumber < MushafPageHelper::MIN_PAGE || $pageNumber > MushafPageHelper::MAX_PAGE) {
            return response()->json(['message' => 'Nomor halaman tidak valid (1–604).'], 422);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    PageProgress::STATUS_BELUM,
                    PageProgress::STATUS_PROSES,
                    PageProgress::STATUS_HAFAL,
                ]),
            ],
        ]);

        $progress = $this->progressService->updatePageStatus($pageNumber, $validated['status']);

        return response()->json([
            'message' => "Status halaman {$pageNumber} diperbarui menjadi " . strtoupper($progress->statusLabel()) . '.',
            'data' => [
                'page_number' => $progress->page_number,
                'status' => $progress->status,
                'status_label' => $progress->statusLabel(),
                'review_count' => $progress->review_count,
                'last_reviewed_at' => $progress->last_reviewed_at?->toIso8601String(),
                'last_reviewed_human' => $progress->last_reviewed_at ? $progress->last_reviewed_at->diffForHumans() : 'Belum pernah',
                'needs_murajaah' => $progress->needsMurajaah(),
            ],
        ]);
    }

    /**
     * Log today's Muraja'ah review ("Catat Muraja'ah Hari Ini") for a given Mushaf page.
     */
    public function logMurajaah(int $pageNumber): JsonResponse
    {
        if ($pageNumber < MushafPageHelper::MIN_PAGE || $pageNumber > MushafPageHelper::MAX_PAGE) {
            return response()->json(['message' => 'Nomor halaman tidak valid (1–604).'], 422);
        }

        $progress = $this->progressService->logPageMurajaah($pageNumber);

        return response()->json([
            'message' => "Alhamdulillah! Muraja'ah halaman {$pageNumber} tercatat (Total {$progress->review_count}x).",
            'data' => [
                'page_number' => $progress->page_number,
                'status' => $progress->status,
                'status_label' => $progress->statusLabel(),
                'review_count' => $progress->review_count,
                'last_reviewed_at' => $progress->last_reviewed_at?->toIso8601String(),
                'last_reviewed_human' => $progress->last_reviewed_at?->diffForHumans() ?? 'Baru saja',
                'needs_murajaah' => $progress->needsMurajaah(),
            ],
        ]);
    }
}

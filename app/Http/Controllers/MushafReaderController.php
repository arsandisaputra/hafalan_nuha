<?php

namespace App\Http\Controllers;

use App\Services\QuranApiService;
use App\Services\TahfidzProgressService;
use App\Support\MushafPageHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MushafReaderController extends Controller
{
    public function __construct(
        private readonly QuranApiService $quranApiService,
        private readonly TahfidzProgressService $progressService
    ) {}

    /**
     * Redirect root URL to the Mushaf Reader at Page 1 (or last visited page via query).
     */
    public function home(Request $request): RedirectResponse
    {
        $page = (int) $request->query('page', 1);
        $page = max(MushafPageHelper::MIN_PAGE, min(MushafPageHelper::MAX_PAGE, $page));

        return redirect()->route('mushaf.show', ['pageNumber' => $page]);
    }

    /**
     * Display the distraction-free Mushaf Reader for a specific page (1–604).
     */
    public function show(Request $request, int $pageNumber = 1): View
    {
        abort_if(
            $pageNumber < MushafPageHelper::MIN_PAGE || $pageNumber > MushafPageHelper::MAX_PAGE,
            404,
            'Halaman Mushaf Madinah hanya tersedia dari halaman 1 hingga 604.'
        );

        $forceRefresh = (bool) $request->boolean('refresh');
        $pageData = $this->quranApiService->getPageData($pageNumber, $forceRefresh);
        $positionMeta = MushafPageHelper::getPagePositionMetadata($pageNumber);
        $userState = $this->progressService->getPageUserState($pageNumber);
        $reciters = $this->quranApiService->getSupportedReciters();

        return view('mushaf.reader', [
            'pageNumber' => $pageNumber,
            'pageData' => $pageData,
            'positionMeta' => $positionMeta,
            'progress' => $userState['progress'],
            'note' => $userState['note'],
            'reciters' => $reciters,
            'defaultReciterId' => (int) config('services.quran_api.default_reciter_id', 7),
            'juzStartPages' => MushafPageHelper::JUZ_START_PAGES,
        ]);
    }

    /**
     * API endpoint returning normalized JSON for a given Mushaf page (for preloading or refreshing).
     */
    public function pageJson(Request $request, int $pageNumber): JsonResponse
    {
        if ($pageNumber < MushafPageHelper::MIN_PAGE || $pageNumber > MushafPageHelper::MAX_PAGE) {
            return response()->json([
                'message' => 'Nomor halaman harus antara 1 sampai 604.',
            ], 422);
        }

        $forceRefresh = (bool) $request->boolean('refresh');
        $pageData = $this->quranApiService->getPageData($pageNumber, $forceRefresh);
        $userState = $this->progressService->getPageUserState($pageNumber);

        return response()->json([
            'page' => $pageData,
            'progress' => [
                'status' => $userState['progress']->status,
                'status_label' => $userState['progress']->statusLabel(),
                'review_count' => $userState['progress']->review_count,
                'last_reviewed_at' => $userState['progress']->last_reviewed_at?->toIso8601String(),
                'last_reviewed_human' => $userState['progress']->last_reviewed_at?->diffForHumans(),
                'needs_murajaah' => $userState['progress']->needsMurajaah(),
            ],
            'note' => [
                'narrative_summary' => $userState['note']->narrative_summary ?? '',
                'verse_links_json' => $userState['note']->verse_links_json ?? [],
                'updated_at' => $userState['note']->updated_at?->toIso8601String(),
            ],
        ]);
    }
}

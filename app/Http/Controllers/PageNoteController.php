<?php

namespace App\Http\Controllers;

use App\Services\TahfidzProgressService;
use App\Support\MushafPageHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageNoteController extends Controller
{
    public function __construct(
        private readonly TahfidzProgressService $progressService
    ) {}

    /**
     * Auto-save Mind-Link / Benang Merah notes and optional per-verse cues for a Mushaf page.
     */
    public function upsert(Request $request, int $pageNumber): JsonResponse
    {
        if ($pageNumber < MushafPageHelper::MIN_PAGE || $pageNumber > MushafPageHelper::MAX_PAGE) {
            return response()->json(['message' => 'Nomor halaman tidak valid (1–604).'], 422);
        }

        $validated = $request->validate([
            'narrative_summary' => ['nullable', 'string', 'max:10000'],
            'verse_links_json' => ['nullable', 'array'],
            'verse_links_json.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = $this->progressService->savePageNote(
            $pageNumber,
            $validated['narrative_summary'] ?? '',
            $validated['verse_links_json'] ?? null
        );

        return response()->json([
            'message' => 'Catatan Benang Merah tersimpan otomatis.',
            'data' => [
                'page_number' => $note->page_number,
                'narrative_summary' => $note->narrative_summary ?? '',
                'verse_links_json' => $note->verse_links_json ?? [],
                'updated_at' => $note->updated_at?->format('H:i:s'),
            ],
        ]);
    }
}

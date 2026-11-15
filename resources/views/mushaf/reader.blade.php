@extends('layouts.app')

@section('title', "Halaman {$pageNumber} ({$pageData['surah_names']}) — Juz {$positionMeta['juz_number']} | HafalanNuha")

@section('content')
<div
    x-data="mushafReaderApp({
        pageNumber: @js($pageNumber),
        juzNumber: @js($positionMeta['juz_number']),
        verses: @js($pageData['verses'] ?? []),
        reciters: @js($reciters),
        defaultReciterId: @js($defaultReciterId),
        initialStatus: @js($progress->status ?? 'belum'),
        initialReviewCount: @js((int) ($progress->review_count ?? 0)),
        initialLastReviewedHuman: @js($progress->last_reviewed_at ? $progress->last_reviewed_at->diffForHumans() : 'Belum pernah'),
        initialNarrative: @js($note->narrative_summary ?? ''),
        initialVerseLinks: @js($note->verse_links_json ?? (object) []),
        csrfToken: @js(csrf_token()),
        urls: {
            progress: @js(route('pages.progress.update', ['pageNumber' => $pageNumber], false)),
            murajaah: @js(route('pages.murajaah.log', ['pageNumber' => $pageNumber], false)),
            notes: @js(route('pages.notes.upsert', ['pageNumber' => $pageNumber], false)),
        }
    })"
    x-init="initReader()"
    @keydown.window="handleGlobalShortcut($event)"
    :style="`--arabic-font-size: ${(fontSizePx / 16).toFixed(3)}rem;`"
    class="min-h-dvh flex flex-col pb-20 md:pb-28"
>
    <!-- =========================================================================
         STICKY TOP BAR: Ultra-Compact on Mobile (Auto-Hides on Scroll Down)
         ========================================================================= -->
    <header
        :class="headerHiddenOnScroll && !focusMode ? '-translate-y-full md:translate-y-0' : 'translate-y-0'"
        class="sticky top-0 z-30 border-b border-[#E5DEC9] dark:border-slate-800 bg-[#FAF8F5]/95 dark:bg-[#0F172A]/95 backdrop-blur-md shadow-sm transition-transform duration-200"
    >
        <div class="max-w-6xl mx-auto px-2.5 sm:px-6 py-1.5 sm:py-2.5 flex flex-col gap-1.5 sm:gap-2">

            <!-- ROW 1: Compact Navigation & Page Identity -->
            <div class="flex items-center justify-between gap-1.5 sm:gap-3">
                <!-- Left: Dashboard Link + Current Surah, Juz & Page Info -->
                <div class="flex items-center gap-2 min-w-0">
                    <a href="{{ route('dashboard.index') }}"
                       class="interactive-pill shrink-0 inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-emerald-700 text-white font-semibold text-xs sm:text-sm shadow-sm hover:bg-emerald-800 transition-colors"
                       title="Dashboard 30 Juz"
                       aria-label="Ke Dashboard 30 Juz HafalanNuha">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span class="hidden sm:inline">30 Juz</span>
                    </a>

                    <button
                        type="button"
                        @click="$refs.juzJumperDialog.showModal()"
                        class="text-left min-w-0 group"
                        title="Ketuk untuk pindah halaman atau Juz"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] sm:text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-800">
                                Juz {{ $positionMeta['juz_number'] }}
                            </span>
                            <h1 class="text-xs sm:text-base font-bold text-slate-900 dark:text-slate-100 truncate group-hover:text-emerald-700 dark:group-hover:text-emerald-400">
                                {{ $pageData['surah_names'] }}
                            </h1>
                        </div>
                        <p class="text-[11px] sm:text-xs text-slate-600 dark:text-slate-400 font-medium truncate">
                            <span class="sm:hidden">Hal. {{ $pageNumber }}/604</span>
                            <span class="hidden sm:inline">{{ $positionMeta['page_label'] }}</span>
                            <span class="mx-1 text-slate-400">•</span>
                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">
                                <span class="sm:hidden">Hal {{ $positionMeta['page_in_juz'] }}/{{ $positionMeta['total_pages_in_juz'] }}</span>
                                <span class="hidden sm:inline">{{ $positionMeta['juz_position_label'] }}</span>
                            </span>
                        </p>
                    </button>
                </div>

                <!-- Right: Prev/Next Page, Settings Drawer Trigger (Mobile), Theme Toggle -->
                <nav aria-label="Navigasi Halaman Mushaf" class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                    @if ($positionMeta['prev_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['prev_page']]) }}"
                           class="interactive-pill inline-flex items-center justify-center px-2 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold hover:border-emerald-500 transition-colors"
                           aria-label="Halaman Sebelumnya (Halaman {{ $positionMeta['prev_page'] }})"
                           title="Halaman {{ $positionMeta['prev_page'] }}">
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="hidden lg:inline ml-1">Hal {{ $positionMeta['prev_page'] }}</span>
                        </a>
                    @endif

                    <!-- Direct Page Input (Desktop & Tablet) -->
                    <form action="{{ route('home') }}" method="GET" class="hidden sm:inline-flex items-center" @submit.prevent="goToPageInput()">
                        <label for="quick-page-select" class="sr-only">Pilih Halaman (1 sampai 604)</label>
                        <input
                            id="quick-page-select"
                            type="number"
                            min="1"
                            max="604"
                            x-model.number="jumpPageInput"
                            @change="goToPageInput()"
                            class="w-14 text-center px-1.5 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-slate-100"
                        >
                    </form>

                    @if ($positionMeta['next_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['next_page']]) }}"
                           class="interactive-pill inline-flex items-center justify-center px-2 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold hover:border-emerald-500 transition-colors"
                           aria-label="Halaman Berikutnya (Halaman {{ $positionMeta['next_page'] }})"
                           title="Halaman {{ $positionMeta['next_page'] }}">
                            <span class="hidden lg:inline mr-1">Hal {{ $positionMeta['next_page'] }}</span>
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endif

                    <!-- Mobile Reader Options Button (Opens Bottom Sheet for Font, Translation, Anchor & Juz Jump) -->
                    <button
                        type="button"
                        @click="$refs.mobileSettingsDialog.showModal()"
                        class="md:hidden inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200"
                        aria-label="Pengaturan Tampilan & Ukuran Huruf">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span>Opsi</span>
                    </button>

                    <!-- Desktop Juz Jumper Button -->
                    <button
                        type="button"
                        @click="$refs.juzJumperDialog.showModal()"
                        class="hidden md:inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold hover:border-emerald-500 transition-colors"
                        aria-label="Pilih Juz atau Halaman">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        <span>Pilih Juz</span>
                    </button>

                    <!-- Dark Mode Toggle -->
                    <button
                        type="button"
                        @click="window.toggleColorScheme(); isDark = document.documentElement.classList.contains('dark')"
                        class="p-1.5 sm:p-2 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:border-emerald-500 transition-colors"
                        aria-label="Ganti Tema Terang atau Gelap">
                        <svg x-show="!isDark" class="w-4 h-4 text-amber-600" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg x-show="isDark" x-cloak class="w-4 h-4 text-emerald-400" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>
                </nav>
            </div>

            <!-- ROW 2: Slim 4-Mode Active Recall Switcher + Desktop Controls -->
            <div x-show="!focusMode" class="flex items-center justify-between gap-2 pt-1 border-t border-[#E5DEC9]/60 dark:border-slate-800">
                <!-- 4 Self-Testing Active Recall Modes (Compact 4-Column Pill Bar on Mobile) -->
                <div role="group" aria-label="Mode Uji Hafalan (Active Recall)" class="grid grid-cols-4 md:inline-flex w-full md:w-auto gap-1 p-0.5 rounded-xl bg-[#F3EFE6] dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700">
                    <button
                        type="button"
                        @click="setRecallMode('baca')"
                        :class="recallMode === 'baca'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2 py-1 rounded-lg text-[11px] sm:text-xs font-semibold text-center truncate transition-all">
                        <span class="sm:hidden">1. Baca</span>
                        <span class="hidden sm:inline">1. Mode Baca</span>
                    </button>

                    <button
                        type="button"
                        @click="setRecallMode('pancingan_awal')"
                        :class="recallMode === 'pancingan_awal'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2 py-1 rounded-lg text-[11px] sm:text-xs font-semibold text-center truncate transition-all"
                        title="Blur seluruh teks Arab kecuali 2–3 kata pertama di setiap ayat">
                        <span class="sm:hidden">2. Awal</span>
                        <span class="hidden sm:inline">2. Pancingan Awal</span>
                    </button>

                    <button
                        type="button"
                        @click="setRecallMode('pancingan_arti')"
                        :class="recallMode === 'pancingan_arti'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2 py-1 rounded-lg text-[11px] sm:text-xs font-semibold text-center truncate transition-all"
                        title="Blur teks Arab dan tampilkan terjemahan Indonesia">
                        <span class="sm:hidden">3. Arti</span>
                        <span class="hidden sm:inline">3. Pancingan Arti</span>
                    </button>

                    <button
                        type="button"
                        @click="setRecallMode('hafalan_penuh')"
                        :class="recallMode === 'hafalan_penuh'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2 py-1 rounded-lg text-[11px] sm:text-xs font-semibold text-center truncate transition-all"
                        title="Uji hafalan penuh: blur seluruh teks Arab dan terjemahan">
                        <span class="sm:hidden">4. Penuh</span>
                        <span class="hidden sm:inline">4. Hafalan Penuh</span>
                    </button>
                </div>

                <!-- Desktop-Only Secondary Toolbar (Hidden on Mobile to keep Mobile Header Ultra-Clean) -->
                <div class="hidden md:flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="highlightAnchors = !highlightAnchors"
                        :aria-pressed="highlightAnchors.toString()"
                        :class="highlightAnchors
                            ? 'bg-amber-500/20 text-amber-900 dark:text-amber-200 border-amber-500/60'
                            : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-[#E5DEC9] dark:border-slate-700'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-semibold transition-colors">
                        <span class="w-2 h-2 rounded-full" :class="highlightAnchors ? 'bg-amber-500' : 'bg-slate-400'"></span>
                        <span>Highlight Awal Ayat</span>
                    </button>

                    <div class="inline-flex items-center gap-1 text-xs">
                        <label for="translation-mode-select" class="text-slate-600 dark:text-slate-400 font-medium">Arti:</label>
                        <select
                            id="translation-mode-select"
                            x-model="translationMode"
                            class="px-2 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <option value="show">Tampilkan Semua</option>
                            <option value="hide">Sembunyikan Semua</option>
                            <option value="peek">Sentuh / Hover</option>
                        </select>
                    </div>

                    <div class="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800">
                        <label for="arabic-font-slider" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Huruf</label>
                        <input
                            id="arabic-font-slider"
                            type="range"
                            min="26"
                            max="40"
                            step="1"
                            x-model.number="fontSizePx"
                            @input="saveFontPreference()"
                            class="w-20 cursor-pointer"
                        >
                        <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400 w-9 text-right" x-text="`${fontSizePx}px`"></span>
                    </div>

                    <button
                        type="button"
                        @click="notesDrawerOpen = !notesDrawerOpen"
                        :aria-expanded="notesDrawerOpen.toString()"
                        :class="notesDrawerOpen
                            ? 'bg-emerald-700 text-white border-emerald-700'
                            : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-[#E5DEC9] dark:border-slate-700'"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border text-xs font-semibold shadow-sm transition-colors">
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Benang Merah</span>
                        <span x-show="narrativeSummary.trim().length > 0" class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- =========================================================================
         MAIN WORKSPACE: Clean Reading Canvas
         ========================================================================= -->
    <div class="max-w-6xl w-full mx-auto px-2 sm:px-6 pt-2.5 sm:pt-5 flex-1 grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        <!-- CENTER COLUMN: Mushaf Page Reading Canvas -->
        <main
            id="mushaf-reading-canvas"
            :class="notesDrawerOpen ? 'lg:col-span-8' : 'lg:col-span-12 max-w-4xl mx-auto w-full'"
            class="transition-all duration-200 space-y-2.5 sm:space-y-4"
        >
            <!-- Compact Single-Line Status & Muraja'ah Strip -->
            <section aria-label="Status Hafalan dan Catatan Muraja'ah Halaman Ini"
                     class="px-3 py-2 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700/80 shadow-sm flex items-center justify-between gap-2">
                <!-- Segmented Status Pills -->
                <div role="group" aria-label="Pilih Status Hafalan Halaman" class="inline-flex items-center rounded-xl p-0.5 bg-[#F3EFE6] dark:bg-slate-900 border border-[#E5DEC9] dark:border-slate-700">
                    <button
                        type="button"
                        @click="updatePageStatus('belum')"
                        :class="pageStatus === 'belum'
                            ? 'bg-slate-700 text-white shadow-sm'
                            : 'text-slate-600 dark:text-slate-400'"
                        class="px-2 sm:px-2.5 py-1 rounded-lg text-[11px] sm:text-xs font-semibold transition-all">
                        Belum
                    </button>
                    <button
                        type="button"
                        @click="updatePageStatus('proses')"
                        :class="pageStatus === 'proses'
                            ? 'bg-amber-500 text-slate-950 shadow-sm'
                            : 'text-slate-600 dark:text-slate-400'"
                        class="px-2 sm:px-2.5 py-1 rounded-lg text-[11px] sm:text-xs font-semibold transition-all">
                        Proses
                    </button>
                    <button
                        type="button"
                        @click="updatePageStatus('hafal')"
                        :class="pageStatus === 'hafal'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-600 dark:text-slate-400'"
                        class="px-2 sm:px-2.5 py-1 rounded-lg text-[11px] sm:text-xs font-semibold transition-all">
                        Hafal
                    </button>
                </div>

                <!-- Right: Compact Muraja'ah Button & Benang Merah Quick Trigger -->
                <div class="flex items-center gap-1.5 sm:gap-2.5">
                    <span class="hidden sm:inline text-xs text-slate-500 dark:text-slate-400" x-text="`Terakhir: ${lastReviewedHuman}`"></span>

                    <button
                        type="button"
                        @click="logMurajaahToday()"
                        :disabled="isLoggingMurajaah"
                        class="inline-flex items-center gap-1 px-2.5 sm:px-3 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-[11px] sm:text-xs font-bold shadow-sm transition-all">
                        <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Muraja'ah</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-emerald-800/80 text-[10px]" x-text="`${reviewCount}x`"></span>
                    </button>

                    <!-- Mobile Benang Merah Button -->
                    <button
                        type="button"
                        @click="$refs.mobileNotesDialog.showModal()"
                        class="lg:hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-900 text-[11px] font-semibold text-slate-700 dark:text-slate-200"
                        title="Catatan Benang Merah Halaman">
                        <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="hidden xs:inline">Catatan</span>
                        <span x-show="narrativeSummary.trim().length > 0" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </button>
                </div>
            </section>

            <!-- Compact Active Recall Helper Strip (Only when blurred mode is active) -->
            <div x-show="recallMode !== 'baca'" x-cloak
                 class="px-3 py-1.5 rounded-xl bg-emerald-950/5 dark:bg-emerald-900/20 border border-emerald-600/30 flex items-center justify-between gap-2 text-[11px] sm:text-xs">
                <span class="text-emerald-900 dark:text-emerald-200 truncate">
                    <strong x-text="recallModeLabel()"></strong> — Ketuk ayat untuk mengintip.
                </span>
                <button
                    type="button"
                    @click="clearAllPeeks()"
                    x-show="Object.keys(peekedVerses).length > 0"
                    class="px-2 py-0.5 rounded-lg bg-emerald-700 text-white font-semibold shrink-0 text-[11px]">
                    Tutup Intipan
                </button>
            </div>

            <!-- Floating Toast Notification -->
            <div x-show="toastMessage !== ''" x-cloak
                 x-transition.opacity
                 role="status"
                 aria-live="polite"
                 class="fixed top-16 inset-x-4 sm:inset-x-auto sm:right-6 sm:w-auto z-50 px-3.5 py-2 rounded-xl bg-emerald-800 text-white text-xs font-semibold shadow-lg flex items-center justify-between gap-2">
                <span x-text="toastMessage"></span>
                <button type="button" @click="toastMessage = ''" class="text-emerald-200 hover:text-white">✕</button>
            </div>

            <!-- =================================================================
                 MUSHAF PAGE FRAME (Maximized Verse Reading Space on Mobile)
                 ================================================================= -->
            <article class="rounded-2xl sm:rounded-3xl bg-[#FAF8F5] dark:bg-[#131C31] border border-[#E5DEC9] sm:border-2 dark:border-slate-700/90 shadow-sm overflow-hidden">
                <!-- Slim Mushaf Frame Top Margin Header -->
                <div class="px-3.5 sm:px-5 py-2 bg-[#F3EFE6] dark:bg-slate-800/90 border-b border-[#E5DEC9] dark:border-slate-700 flex items-center justify-between text-[11px] sm:text-xs font-semibold text-slate-700 dark:text-slate-300">
                    <span>Juz {{ $positionMeta['juz_number'] }}</span>
                    <span class="font-bold text-emerald-800 dark:text-emerald-300 truncate mx-2">{{ $pageData['surah_names'] }}</span>
                    <span>Hal. {{ $pageNumber }}</span>
                </div>

                @if (! empty($pageData['is_fallback']) && empty($pageData['verses']))
                    <div class="p-6 sm:p-8 text-center space-y-3">
                        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">Halaman Belum Tersimpan di Cache Lokal</h2>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-lg mx-auto">
                            {{ $pageData['offline_message'] ?? 'Silakan hubungkan ke internet untuk mengunduh halaman ini dari Quran.com API v4.' }}
                        </p>
                        <div class="pt-2">
                            <a href="{{ route('mushaf.show', ['pageNumber' => $pageNumber, 'refresh' => 1]) }}"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700">
                                Muat Ulang dari Quran.com API
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Verses List on Active Mushaf Page -->
                    <div class="divide-y divide-[#E5DEC9]/60 dark:divide-slate-800/80">
                        <template x-for="(verse, idx) in verses" :key="verse.verse_key">
                            <div
                                :id="`verse-card-${idx}`"
                                :class="{
                                    'bg-emerald-50/90 dark:bg-emerald-950/35 ring-2 ring-inset ring-emerald-500/60': currentVerseIndex === idx && isPlaying,
                                    'bg-amber-50/40 dark:bg-amber-950/15': currentVerseIndex === idx && !isPlaying
                                }"
                                class="px-3.5 py-3 sm:p-6 transition-colors duration-200"
                            >
                                <!-- Surah Banner Header if Verse 1 -->
                                <template x-if="verse.is_surah_start">
                                    <div class="mb-3.5 sm:mb-5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-teal-900 text-white p-3 sm:p-4 text-center shadow-sm border border-emerald-600/30">
                                        <div class="flex items-center justify-between text-[11px] text-emerald-200 font-medium">
                                            <span x-text="`Surah ke-${verse.chapter_id}`"></span>
                                            <span x-text="verse.surah_name_id"></span>
                                        </div>
                                        <h2 class="text-base sm:text-xl font-bold tracking-wide mt-0.5">
                                            <span x-text="`Surah ${verse.surah_name}`"></span>
                                            <span class="font-arabic text-xl sm:text-2xl ml-2" dir="rtl" x-text="verse.surah_name_arabic"></span>
                                        </h2>
                                        <template x-if="verse.show_bismillah">
                                            <p class="arabic-uthmani text-center text-emerald-100 mt-1.5 text-xl sm:text-2xl" dir="rtl">
                                                بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ
                                            </p>
                                        </template>
                                    </div>
                                </template>

                                <!-- Minimal Unobtrusive Verse Meta Row -->
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <div class="flex items-center gap-1.5">
                                        <!-- Compact Play + Verse Key Button (Combined to save vertical & horizontal space on mobile!) -->
                                        <button
                                            type="button"
                                            @click="playSingleVerse(idx)"
                                            :class="currentVerseIndex === idx && isPlaying
                                                ? 'bg-emerald-600 text-white shadow-sm'
                                                : 'bg-[#F3EFE6] dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-[#E5DEC9] dark:border-slate-700 hover:border-emerald-500'"
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-[11px] font-bold transition-colors"
                                            :aria-label="`Putar Audio Ayat ${verse.verse_key}`">
                                            <svg x-show="!(currentVerseIndex === idx && isPlaying)" class="w-3 h-3 text-emerald-600 dark:text-emerald-400" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                                            </svg>
                                            <svg x-show="currentVerseIndex === idx && isPlaying" x-cloak class="w-3 h-3 animate-pulse" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zm7 0a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" clip-rule="evenodd" />
                                            </svg>
                                            <span x-text="verse.verse_key"></span>
                                        </button>

                                        <!-- Active Repeat Badge -->
                                        <span
                                            x-show="currentVerseIndex === idx && isPlaying && maxVerseRepeats() > 1"
                                            x-cloak
                                            class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-800 dark:text-amber-300 text-[10px] font-bold"
                                            x-text="`${currentVerseRepeat + 1}/${maxVerseRepeats()}x`"
                                        ></span>

                                        <!-- Personal Cue Chip (If written) -->
                                        <template x-if="verseLinks[verse.verse_key]">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-100/80 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200 text-[11px] font-medium truncate max-w-[170px] sm:max-w-xs"
                                                  x-text="`🔗 ${verseLinks[verse.verse_key]}`">
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Tap-to-Peek Toggle Pill (Only shown when in a blurred recall mode) -->
                                    <button
                                        type="button"
                                        x-show="recallMode !== 'baca'"
                                        @click="togglePeekVerse(verse.verse_key)"
                                        :class="isVersePeeked(verse.verse_key)
                                            ? 'bg-amber-500 text-slate-950'
                                            : 'bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                                        class="px-2 py-0.5 rounded-md text-[11px] font-semibold transition-colors">
                                        <span x-text="isVersePeeked(verse.verse_key) ? 'Tutup' : 'Intip'"></span>
                                    </button>
                                </div>

                                <!-- ARABIC UTHMANI VERSE BLOCK (Primary Focus on Mobile & Desktop) -->
                                <div
                                    dir="rtl"
                                    lang="ar"
                                    @click="handleVerseTap(verse.verse_key)"
                                    @mousedown="startHoldPeek(verse.verse_key)"
                                    @mouseup="endHoldPeek(verse.verse_key)"
                                    @touchstart.passive="startHoldPeek(verse.verse_key)"
                                    @touchend.passive="endHoldPeek(verse.verse_key)"
                                    :class="recallMode !== 'baca' ? 'cursor-pointer select-none' : ''"
                                    class="arabic-uthmani py-1 sm:py-2 px-0.5 rounded-xl text-slate-900 dark:text-slate-100"
                                >
                                    <!-- First 2-3 Anchor Words -->
                                    <span
                                        :class="{
                                            'bg-amber-200/70 dark:bg-amber-500/25 text-emerald-950 dark:text-amber-200 px-1.5 py-0.5 rounded-lg border-b-2 border-amber-500 font-semibold': highlightAnchors || recallMode === 'pancingan_awal',
                                            'recall-blur': isFirstWordsBlurred(verse.verse_key),
                                            'recall-unblurred': !isFirstWordsBlurred(verse.verse_key)
                                        }"
                                        x-text="verse.first_words"
                                    ></span>

                                    <!-- Remaining Arabic Words of the Verse -->
                                    <span
                                        :class="{
                                            'recall-blur': isRemainingWordsBlurred(verse.verse_key),
                                            'recall-unblurred': !isRemainingWordsBlurred(verse.verse_key)
                                        }"
                                        x-text="verse.remaining_words ? ' ' + verse.remaining_words : ''"
                                    ></span>

                                    <!-- End of Ayah Marker -->
                                    <span class="inline-flex items-center justify-center mx-1 px-2 py-0.5 rounded-full text-sm sm:text-base font-bold bg-emerald-100/90 dark:bg-emerald-950/90 text-emerald-800 dark:text-emerald-300 border border-emerald-500/40 align-middle">
                                        <span x-text="toArabicNumerals(verse.verse_number)"></span>
                                    </span>
                                </div>

                                <!-- INDONESIAN TRANSLATION (Kemenag RI / Resource ID 33) -->
                                <div
                                    x-show="shouldShowTranslationBlock()"
                                    @click="togglePeekTranslation(verse.verse_key)"
                                    :class="{
                                        'recall-blur cursor-pointer': isTranslationBlurred(verse.verse_key),
                                        'recall-unblurred': !isTranslationBlurred(verse.verse_key)
                                    }"
                                    class="mt-1.5 sm:mt-2.5 pt-1.5 sm:pt-2 border-t border-[#E5DEC9]/40 dark:border-slate-800/80 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed"
                                >
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400 mr-1" x-text="`${verse.verse_number}.`"></span>
                                    <span x-text="verse.translation_id"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                <!-- Mushaf Frame Bottom Footer Navigation -->
                <div class="px-3.5 sm:px-5 py-3 bg-[#F3EFE6] dark:bg-slate-800/90 border-t border-[#E5DEC9] dark:border-slate-700 flex items-center justify-between">
                    @if ($positionMeta['prev_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['prev_page']]) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-[#E5DEC9] dark:border-slate-700 text-xs font-bold hover:border-emerald-500 transition-colors">
                            ← Hal {{ $positionMeta['prev_page'] }}
                        </a>
                    @else
                        <span></span>
                    @endif

                    <span class="text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-slate-400">
                        Hal {{ $pageNumber }} / 604 • Juz {{ $positionMeta['juz_number'] }}
                    </span>

                    @if ($positionMeta['next_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['next_page']]) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                            Hal {{ $positionMeta['next_page'] }} →
                        </a>
                    @else
                        <span></span>
                    @endif
                </div>
            </article>
        </main>

        <!-- =====================================================================
             DESKTOP RIGHT SIDEBAR: Phase 2 Mind-Link ("Benang Merah")
             ===================================================================== -->
        <aside
            x-show="notesDrawerOpen"
            x-cloak
            aria-label="Panel Catatan Benang Merah dan Tadabbur Halaman"
            class="hidden lg:block lg:col-span-4 lg:sticky lg:top-28 rounded-2xl bg-white dark:bg-slate-800/95 border border-[#E5DEC9] dark:border-slate-700 shadow-lg p-5 space-y-4"
        >
            <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-700 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Benang Merah & Tadabbur</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Halaman {{ $pageNumber }} (Juz {{ $positionMeta['juz_number'] }})
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md"
                          :class="{
                              'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': noteSaveState === 'saving',
                              'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': noteSaveState === 'saved',
                              'bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-400': noteSaveState === 'idle'
                          }"
                          x-text="noteSaveState === 'saving' ? 'Menyimpan...' : (noteSaveState === 'saved' ? 'Tersimpan ✓' : 'Auto-save')">
                    </span>
                    <button type="button" @click="notesDrawerOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-700" aria-label="Tutup Panel">✕</button>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="page-narrative-textarea" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    Alur Cerita / Koneksi Antar-Ayat:
                </label>
                <textarea
                    id="page-narrative-textarea"
                    rows="6"
                    x-model="narrativeSummary"
                    @input.debounce.800ms="savePageNotes()"
                    @blur="savePageNotes()"
                    placeholder="Contoh: Ayat 1-3: Sifat orang bertakwa -> Ayat 4-5: Balasan mereka..."
                    class="w-full rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-900/90 p-3 text-xs sm:text-sm text-slate-800 dark:text-slate-100 leading-relaxed resize-y"
                ></textarea>
            </div>

            <div class="space-y-2 pt-2 border-t border-[#E5DEC9] dark:border-slate-700">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">Kata Kunci Per Ayat:</h3>
                <div class="max-h-64 overflow-y-auto pr-1 space-y-2">
                    <template x-for="verse in verses" :key="`note-desktop-${verse.verse_key}`">
                        <div class="flex items-center gap-2">
                            <span class="w-12 shrink-0 text-center py-1 rounded-md bg-[#F3EFE6] dark:bg-slate-900 text-[11px] font-bold" x-text="verse.verse_key"></span>
                            <input
                                type="text"
                                x-model="verseLinks[verse.verse_key]"
                                @input.debounce.800ms="savePageNotes()"
                                @blur="savePageNotes()"
                                :placeholder="`Kata kunci ayat ${verse.verse_number}...`"
                                class="flex-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-900 px-2.5 py-1 text-xs"
                            >
                        </div>
                    </template>
                </div>
            </div>
        </aside>
    </div>

    <!-- =========================================================================
         STICKY BOTTOM AUDIO BAR: Single-Line Ultra-Slim on Mobile!
         ========================================================================= -->
    <footer aria-label="Pemutar Audio Murottal Halaman"
            class="fixed bottom-0 inset-x-0 z-40 border-t border-[#E5DEC9] dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md shadow-2xl">

        <!-- Ultra-Thin Top Edge Audio Progress Bar (Takes 0 extra vertical space on mobile) -->
        <div class="w-full h-1 bg-[#EFECE6] dark:bg-slate-800 overflow-hidden">
            <div class="h-full bg-emerald-600 transition-all duration-150"
                 :style="`width: ${audioDuration > 0 ? Math.min(100, (audioCurrentTime / audioDuration) * 100) : 0}%`"></div>
        </div>

        <div class="max-w-6xl mx-auto px-2.5 sm:px-6 py-1.5 sm:py-2.5 flex flex-col gap-1.5">
            <!-- Shadowing Countdown Banner (Only shown when waiting between verses) -->
            <div x-show="shadowingCountdown > 0" x-cloak
                 class="px-2.5 py-1 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-900 dark:text-amber-200 text-[11px] font-semibold flex items-center justify-between">
                <span>🎙️ Tirukan ayat (<strong x-text="`${shadowingCountdown}d`"></strong>)...</span>
                <button type="button" @click="skipShadowingPause()" class="underline font-bold">Lanjut →</button>
            </div>

            <!-- Single-Row Transport + Audio Controls -->
            <div class="flex items-center justify-between gap-2">
                <!-- Left: Prev, Play/Pause, Next & Active Verse Indicator -->
                <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0">
                    <button
                        type="button"
                        @click="prevVerse()"
                        :disabled="verses.length === 0"
                        class="p-1.5 sm:p-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 hover:border-emerald-500 disabled:opacity-40"
                        aria-label="Ayat Sebelumnya">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        @click="togglePlayPause()"
                        :disabled="verses.length === 0"
                        class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md inline-flex items-center gap-1.5 transition-all shrink-0">
                        <svg x-show="!isPlaying" class="w-3.5 h-3.5 sm:w-4 sm:h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                        </svg>
                        <svg x-show="isPlaying" x-cloak class="w-3.5 h-3.5 sm:w-4 sm:h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zm7 0a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" clip-rule="evenodd" />
                        </svg>
                        <span x-text="isPlaying ? 'Jeda' : 'Putar'"></span>
                    </button>

                    <button
                        type="button"
                        @click="nextVerse()"
                        :disabled="verses.length === 0"
                        class="p-1.5 sm:p-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 hover:border-emerald-500 disabled:opacity-40"
                        aria-label="Ayat Berikutnya">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798L4.555 5.168z" />
                        </svg>
                    </button>

                    <!-- Compact Active Verse Info -->
                    <div class="text-xs min-w-0 truncate">
                        <div class="font-bold text-slate-900 dark:text-slate-100 truncate">
                            <span x-text="activeVerse() ? activeVerse().verse_key : 'Siap'"></span>
                            <span class="text-slate-400 font-normal text-[11px]" x-text="`(${currentVerseIndex + 1}/${verses.length})`"></span>
                        </div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate" x-text="`${formatTime(audioCurrentTime)} / ${formatTime(audioDuration)}`"></div>
                    </div>
                </div>

                <!-- Right (Mobile): Single Compact Audio Settings Trigger + Focus Mode Button -->
                <div class="flex md:hidden items-center gap-1.5 shrink-0">
                    <button
                        type="button"
                        @click="$refs.mobileAudioDialog.showModal()"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 text-[11px] font-bold text-slate-700 dark:text-slate-200">
                        <span x-text="loopModeShortLabel()"></span>
                        <span>•</span>
                        <span class="text-emerald-700 dark:text-emerald-400" x-text="`${playbackSpeed}x`"></span>
                        <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Right (Desktop): Full Inline Audio Selectors -->
                <div class="hidden md:flex flex-wrap items-center gap-2 text-xs">
                    <select
                        aria-label="Mode Pengulangan Audio"
                        x-model="loopMode"
                        class="px-2.5 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-semibold">
                        <option value="page">🔁 Loop Halaman</option>
                        <option value="verse_3">🔂 Ulang Ayat 3x</option>
                        <option value="verse_5">🔂 Ulang Ayat 5x</option>
                        <option value="verse_1">➡️ Sekali Jalan (1x)</option>
                    </select>

                    <select
                        aria-label="Pilih Qari"
                        x-model.number="selectedReciterId"
                        @change="onReciterChange()"
                        class="px-2.5 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-semibold max-w-[180px] truncate">
                        @foreach ($reciters as $id => $qari)
                            <option value="{{ $id }}">{{ $qari['name'] }}</option>
                        @endforeach
                    </select>

                    <select
                        aria-label="Kecepatan Audio"
                        x-model.number="playbackSpeed"
                        @change="applyAudioSettings()"
                        class="px-2 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-bold text-emerald-700 dark:text-emerald-400">
                        <option value="0.75">0.75x</option>
                        <option value="1">1.0x</option>
                        <option value="1.25">1.25x</option>
                        <option value="1.5">1.5x</option>
                    </select>

                    <select
                        aria-label="Jeda Antar Ayat"
                        x-model.number="pauseIntervalSec"
                        class="px-2 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-semibold">
                        <option value="0">Jeda 0d</option>
                        <option value="2">Jeda 2d</option>
                        <option value="5">Jeda 5d</option>
                    </select>
                </div>
            </div>
        </div>
    </footer>

    <!-- =========================================================================
         MOBILE BOTTOM SHEET 1: Reader & Typography Options (<dialog closedby="any">)
         ========================================================================= -->
    <dialog
        x-ref="mobileSettingsDialog"
        closedby="any"
        class="rounded-3xl bg-[#FAF8F5] dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-[#E5DEC9] dark:border-slate-700 shadow-2xl p-0 w-11/12 max-w-md"
    >
        <div class="p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-800 pb-3">
                <h2 class="text-sm font-bold">Pengaturan Tampilan Mushaf</h2>
                <form method="dialog">
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-xs font-bold">Selesai</button>
                </form>
            </div>

            <!-- Jump to Page Input -->
            <div class="flex items-center justify-between gap-2">
                <label for="mobile-page-jump" class="text-xs font-bold">Lompat ke Halaman (1–604):</label>
                <form class="flex items-center gap-1.5" @submit.prevent="goToPageInput()">
                    <input
                        id="mobile-page-jump"
                        type="number"
                        min="1"
                        max="604"
                        x-model.number="jumpPageInput"
                        class="w-20 text-center px-2 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold"
                    >
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Buka</button>
                </form>
            </div>

            <!-- Arabic Font Size Slider -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs font-bold">
                    <label for="mobile-font-slider">Ukuran Huruf Arab:</label>
                    <span class="text-emerald-700 dark:text-emerald-400" x-text="`${fontSizePx}px`"></span>
                </div>
                <input
                    id="mobile-font-slider"
                    type="range"
                    min="24"
                    max="40"
                    step="1"
                    x-model.number="fontSizePx"
                    @input="saveFontPreference()"
                    class="w-full cursor-pointer"
                >
            </div>

            <!-- Translation Visibility -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold">Terjemahan Kemenag RI:</label>
                <div class="grid grid-cols-3 gap-1.5 text-xs">
                    <button type="button" @click="translationMode = 'show'"
                            :class="translationMode === 'show' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">Tampil</button>
                    <button type="button" @click="translationMode = 'hide'"
                            :class="translationMode === 'hide' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">Sembunyi</button>
                    <button type="button" @click="translationMode = 'peek'"
                            :class="translationMode === 'peek' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">Sentuh</button>
                </div>
            </div>

            <!-- Highlight Awal Ayat Toggle -->
            <div class="flex items-center justify-between pt-1">
                <div>
                    <div class="text-xs font-bold">Highlight 2–3 Kata Awal Ayat</div>
                    <div class="text-[11px] text-slate-500">Bantu mengunci transisi awal ayat</div>
                </div>
                <button
                    type="button"
                    @click="highlightAnchors = !highlightAnchors"
                    :class="highlightAnchors ? 'bg-amber-500 text-slate-950' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-colors"
                    x-text="highlightAnchors ? 'Aktif' : 'Nonaktif'">
                </button>
            </div>

            <!-- Open 30 Juz Selector -->
            <div class="pt-2 border-t border-[#E5DEC9] dark:border-slate-800 flex justify-between gap-2">
                <button
                    type="button"
                    @click="$refs.mobileSettingsDialog.close(); $refs.juzJumperDialog.showModal()"
                    class="w-full py-2.5 rounded-xl bg-[#F3EFE6] dark:bg-slate-800 text-xs font-bold">
                    Pilih dari Daftar 30 Juz →
                </button>
            </div>
        </div>
    </dialog>

    <!-- =========================================================================
         MOBILE BOTTOM SHEET 2: Audio & Qari Settings (<dialog closedby="any">)
         ========================================================================= -->
    <dialog
        x-ref="mobileAudioDialog"
        closedby="any"
        class="rounded-3xl bg-[#FAF8F5] dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-[#E5DEC9] dark:border-slate-700 shadow-2xl p-0 w-11/12 max-w-md"
    >
        <div class="p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-800 pb-3">
                <h2 class="text-sm font-bold">Pengaturan Audio & Qari</h2>
                <form method="dialog">
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-xs font-bold">Selesai</button>
                </form>
            </div>

            <!-- Seek Bar inside Audio Sheet -->
            <div class="space-y-1">
                <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                    <span x-text="formatTime(audioCurrentTime)"></span>
                    <span x-text="formatTime(audioDuration)"></span>
                </div>
                <input
                    type="range"
                    min="0"
                    :max="audioDuration > 0 ? audioDuration : 100"
                    step="0.1"
                    :value="audioCurrentTime"
                    @input="seekAudio($event.target.value)"
                    class="w-full cursor-pointer"
                >
            </div>

            <!-- Qari Selector -->
            <div class="space-y-1.5">
                <label for="mobile-qari-select" class="block text-xs font-bold">Pilih Qari (Murottal):</label>
                <select
                    id="mobile-qari-select"
                    x-model.number="selectedReciterId"
                    @change="onReciterChange()"
                    class="w-full px-3 py-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold">
                    @foreach ($reciters as $id => $qari)
                        <option value="{{ $id }}">{{ $qari['name'] }} ({{ $qari['style'] }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Loop Mode -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold">Mode Pengulangan (Loop):</label>
                <div class="grid grid-cols-2 gap-1.5 text-xs">
                    <button type="button" @click="loopMode = 'page'"
                            :class="loopMode === 'page' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">🔁 Loop Halaman</button>
                    <button type="button" @click="loopMode = 'verse_3'"
                            :class="loopMode === 'verse_3' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">🔂 Ulang Ayat 3x</button>
                    <button type="button" @click="loopMode = 'verse_5'"
                            :class="loopMode === 'verse_5' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">🔂 Ulang Ayat 5x</button>
                    <button type="button" @click="loopMode = 'verse_1'"
                            :class="loopMode === 'verse_1' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl font-semibold">➡️ Sekali Jalan</button>
                </div>
            </div>

            <!-- Speed & Shadowing Pause -->
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label for="mobile-speed-select" class="block text-xs font-bold">Kecepatan:</label>
                    <select
                        id="mobile-speed-select"
                        x-model.number="playbackSpeed"
                        @change="applyAudioSettings()"
                        class="w-full px-3 py-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                        <option value="0.75">0.75x</option>
                        <option value="1">1.0x (Normal)</option>
                        <option value="1.25">1.25x</option>
                        <option value="1.5">1.5x</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="mobile-pause-select" class="block text-xs font-bold">Jeda Tirukan:</label>
                    <select
                        id="mobile-pause-select"
                        x-model.number="pauseIntervalSec"
                        class="w-full px-3 py-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                        <option value="0">Tanpa Jeda (0d)</option>
                        <option value="2">Jeda 2 Detik</option>
                        <option value="5">Jeda 5 Detik</option>
                    </select>
                </div>
            </div>
        </div>
    </dialog>

    <!-- =========================================================================
         MOBILE BOTTOM SHEET 3: Benang Merah & Tadabbur Notes (<dialog closedby="any">)
         ========================================================================= -->
    <dialog
        x-ref="mobileNotesDialog"
        closedby="any"
        class="rounded-3xl bg-[#FAF8F5] dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-[#E5DEC9] dark:border-slate-700 shadow-2xl p-0 w-11/12 max-w-lg"
    >
        <div class="p-5 space-y-4 max-h-[82dvh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-800 pb-3">
                <div>
                    <h2 class="text-sm font-bold">Benang Merah & Tadabbur</h2>
                    <p class="text-[11px] text-slate-500">Halaman {{ $pageNumber }} • Tersimpan Otomatis</p>
                </div>
                <form method="dialog">
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-xs font-bold">Tutup</button>
                </form>
            </div>

            <div class="space-y-1.5">
                <label for="mobile-narrative-textarea" class="block text-xs font-bold">Alur Cerita Halaman Ini:</label>
                <textarea
                    id="mobile-narrative-textarea"
                    rows="5"
                    x-model="narrativeSummary"
                    @input.debounce.800ms="savePageNotes()"
                    @blur="savePageNotes()"
                    placeholder="Tulis hubungan makna antar-ayat di halaman ini..."
                    class="w-full rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-xs leading-relaxed"
                ></textarea>
            </div>

            <div class="space-y-2">
                <div class="text-xs font-bold">Kata Kunci Per Ayat:</div>
                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                    <template x-for="verse in verses" :key="`note-mobile-${verse.verse_key}`">
                        <div class="flex items-center gap-2">
                            <span class="w-12 shrink-0 text-center py-1 rounded-md bg-[#F3EFE6] dark:bg-slate-800 text-[11px] font-bold" x-text="verse.verse_key"></span>
                            <input
                                type="text"
                                x-model="verseLinks[verse.verse_key]"
                                @input.debounce.800ms="savePageNotes()"
                                @blur="savePageNotes()"
                                :placeholder="`Kata kunci ayat ${verse.verse_number}...`"
                                class="flex-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1 text-xs"
                            >
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </dialog>

    <!-- =========================================================================
         NATIVE MODAL DIALOG (<dialog closedby="any">): Quick 30-Juz Jumper
         ========================================================================= -->
    <dialog
        x-ref="juzJumperDialog"
        closedby="any"
        class="rounded-3xl bg-[#FAF8F5] dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-[#E5DEC9] dark:border-slate-700 shadow-2xl p-0 w-11/12 max-w-2xl"
    >
        <div class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-800 pb-3">
                <div>
                    <h2 class="text-sm sm:text-base font-bold">Navigasi Cepat 30 Juz Mushaf Madinah</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Standar 15 Baris (20 Halaman per Juz • 604 Halaman)</p>
                </div>
                <form method="dialog">
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-xs font-bold">
                        Tutup
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 max-h-80 overflow-y-auto p-1">
                @foreach ($juzStartPages as $juz => $startPage)
                    <a href="{{ route('mushaf.show', ['pageNumber' => $startPage]) }}"
                       class="p-2.5 rounded-xl border text-center transition-all {{ $positionMeta['juz_number'] === $juz ? 'bg-emerald-600 text-white border-emerald-600 font-bold shadow-sm' : 'bg-white dark:bg-slate-800 border-[#E5DEC9] dark:border-slate-700 hover:border-emerald-500' }}">
                        <div class="text-xs font-bold">Juz {{ $juz }}</div>
                        <div class="text-[11px] opacity-80">Hal. {{ $startPage }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </dialog>
</div>

<script>
function mushafReaderApp(config) {
    return {
        pageNumber: config.pageNumber,
        juzNumber: config.juzNumber,
        verses: config.verses || [],
        reciters: config.reciters || {},
        selectedReciterId: Number(localStorage.getItem('hn_reciter_id') || config.defaultReciterId || 7),
        jumpPageInput: config.pageNumber,

        // UI & Mobile Responsiveness State
        isDark: document.documentElement.classList.contains('dark'),
        fontSizePx: Number(localStorage.getItem('hn_font_px') || (window.innerWidth < 640 ? 29 : 32)),
        notesDrawerOpen: window.innerWidth >= 1024 && (config.initialNarrative || '').length > 0,
        focusMode: false,
        headerHiddenOnScroll: false,
        lastScrollY: 0,
        toastMessage: '',

        // Phase 2 & Phase 3 State
        recallMode: localStorage.getItem('hn_recall_mode') || 'baca',
        translationMode: localStorage.getItem('hn_translation_mode') || 'show',
        highlightAnchors: localStorage.getItem('hn_highlight_anchors') === 'true',
        peekedVerses: {},
        peekedTranslations: {},
        holdTimer: null,

        // Phase 4: Page Progress & Muraja'ah State
        pageStatus: config.initialStatus || 'belum',
        reviewCount: config.initialReviewCount || 0,
        lastReviewedHuman: config.initialLastReviewedHuman || 'Belum pernah',
        isLoggingMurajaah: false,

        // Phase 2: Mind-Link / Benang Merah Notes State
        narrativeSummary: config.initialNarrative || '',
        verseLinks: Object.assign({}, config.initialVerseLinks || {}),
        noteSaveState: 'idle',

        // Phase 1: Seamless Audio Engine State
        audioEl: null,
        isPlaying: false,
        audioCurrentTime: 0,
        audioDuration: 0,
        currentVerseIndex: 0,
        currentVerseRepeat: 0,
        loopMode: localStorage.getItem('hn_loop_mode') || 'page',
        playbackSpeed: Number(localStorage.getItem('hn_speed') || 1.0),
        pauseIntervalSec: Number(localStorage.getItem('hn_pause_sec') || 0),
        shadowingCountdown: 0,
        shadowingTimer: null,

        initReader() {
            this.audioEl = new Audio();
            this.audioEl.preload = 'auto';

            this.audioEl.addEventListener('timeupdate', () => {
                this.audioCurrentTime = this.audioEl.currentTime || 0;
            });

            this.audioEl.addEventListener('loadedmetadata', () => {
                this.audioDuration = Number.isFinite(this.audioEl.duration) ? this.audioEl.duration : 0;
            });

            this.audioEl.addEventListener('ended', () => {
                this.handleVerseAudioEnded();
            });

            this.audioEl.addEventListener('error', () => {
                this.showToast('Audio sedang dimuat atau koneksi ke server Qari terputus.');
                this.isPlaying = false;
            });

            // Auto-hide top bar on mobile scroll down so verses get 100% of screen height
            window.addEventListener('scroll', () => {
                const currentY = window.scrollY;
                if (window.innerWidth < 768) {
                    if (currentY > 90 && currentY > this.lastScrollY + 8) {
                        this.headerHiddenOnScroll = true;
                    } else if (currentY < this.lastScrollY - 8 || currentY <= 40) {
                        this.headerHiddenOnScroll = false;
                    }
                } else {
                    this.headerHiddenOnScroll = false;
                }
                this.lastScrollY = currentY;
            }, { passive: true });

            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', () => this.togglePlayPause());
                navigator.mediaSession.setActionHandler('pause', () => this.pauseAudio());
                navigator.mediaSession.setActionHandler('previoustrack', () => this.prevVerse());
                navigator.mediaSession.setActionHandler('nexttrack', () => this.nextVerse());
            }

            this.$watch('loopMode', (val) => localStorage.setItem('hn_loop_mode', val));
            this.$watch('translationMode', (val) => localStorage.setItem('hn_translation_mode', val));
            this.$watch('highlightAnchors', (val) => localStorage.setItem('hn_highlight_anchors', String(val)));
            this.$watch('pauseIntervalSec', (val) => localStorage.setItem('hn_pause_sec', String(val)));
        },

        loopModeShortLabel() {
            return {
                page: '🔁 Hal',
                verse_3: '🔂 3x',
                verse_5: '🔂 5x',
                verse_1: '➡️ 1x',
            }[this.loopMode] || '🔁 Hal';
        },

        handleGlobalShortcut(event) {
            const tag = (event.target && event.target.tagName) ? event.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (event.code === 'Space') {
                event.preventDefault();
                this.togglePlayPause();
            } else if (event.key === '1') {
                this.setRecallMode('baca');
            } else if (event.key === '2') {
                this.setRecallMode('pancingan_awal');
            } else if (event.key === '3') {
                this.setRecallMode('pancingan_arti');
            } else if (event.key === '4') {
                this.setRecallMode('hafalan_penuh');
            } else if (event.key === 'h' || event.key === 'H') {
                this.highlightAnchors = !this.highlightAnchors;
            } else if (event.key === 'b' || event.key === 'B') {
                this.notesDrawerOpen = !this.notesDrawerOpen;
            } else if (event.key === '[') {
                this.prevVerse();
            } else if (event.key === ']') {
                this.nextVerse();
            }
        },

        seekAudio(value) {
            const targetTime = Number(value) || 0;
            this.audioCurrentTime = targetTime;
            if (this.audioEl && Number.isFinite(this.audioEl.duration)) {
                this.audioEl.currentTime = targetTime;
            }
        },

        formatTime(seconds) {
            const s = Math.max(0, Math.floor(Number(seconds) || 0));
            const mins = Math.floor(s / 60);
            const secs = s % 60;
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        },

        saveFontPreference() {
            localStorage.setItem('hn_font_px', String(this.fontSizePx));
        },

        goToPageInput() {
            const target = Math.max(1, Math.min(604, Number(this.jumpPageInput) || 1));
            window.location.href = `/mushaf/${target}`;
        },

        activeVerse() {
            return this.verses[this.currentVerseIndex] || null;
        },

        maxVerseRepeats() {
            if (this.loopMode === 'verse_3') return 3;
            if (this.loopMode === 'verse_5') return 5;
            return 1;
        },

        getVerseAudioUrl(verse) {
            if (!verse) return '';
            if (verse.audio_by_reciter && verse.audio_by_reciter[this.selectedReciterId]) {
                return verse.audio_by_reciter[this.selectedReciterId];
            }
            return verse.audio_url || '';
        },

        togglePlayPause() {
            if (this.verses.length === 0) return;

            if (this.isPlaying) {
                this.pauseAudio();
            } else {
                this.playCurrentVerse(false);
            }
        },

        playSingleVerse(index) {
            this.clearShadowingTimer();
            this.currentVerseIndex = index;
            this.currentVerseRepeat = 0;
            this.playCurrentVerse(true);
        },

        playCurrentVerse(resetSource = true) {
            const verse = this.activeVerse();
            if (!verse) return;

            const url = this.getVerseAudioUrl(verse);
            if (!url) return;

            if (resetSource || this.audioEl.src !== url) {
                this.audioEl.src = url;
            }

            this.audioEl.playbackRate = this.playbackSpeed;
            this.isPlaying = true;
            this.scrollToActiveVerse();

            if ('mediaSession' in navigator && typeof MediaMetadata !== 'undefined') {
                const qari = this.reciters[this.selectedReciterId];
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: `QS. ${verse.surah_name} [${verse.verse_key}]`,
                    artist: qari ? qari.name : 'HafalanNuha',
                    album: `Halaman ${this.pageNumber} • Juz ${this.juzNumber}`,
                });
            }

            this.audioEl.play().catch(() => {
                this.isPlaying = false;
            });
        },

        pauseAudio() {
            this.clearShadowingTimer();
            this.isPlaying = false;
            if (this.audioEl) {
                this.audioEl.pause();
            }
        },

        nextVerse() {
            this.clearShadowingTimer();
            this.currentVerseRepeat = 0;
            if (this.currentVerseIndex < this.verses.length - 1) {
                this.currentVerseIndex++;
                if (this.isPlaying) {
                    this.playCurrentVerse(true);
                } else {
                    this.scrollToActiveVerse();
                }
            } else if (this.loopMode === 'page') {
                this.currentVerseIndex = 0;
                if (this.isPlaying) {
                    this.playCurrentVerse(true);
                }
            }
        },

        prevVerse() {
            this.clearShadowingTimer();
            this.currentVerseRepeat = 0;
            if (this.currentVerseIndex > 0) {
                this.currentVerseIndex--;
            } else {
                this.currentVerseIndex = Math.max(0, this.verses.length - 1);
            }

            if (this.isPlaying) {
                this.playCurrentVerse(true);
            } else {
                this.scrollToActiveVerse();
            }
        },

        handleVerseAudioEnded() {
            const targetRepeats = this.maxVerseRepeats();

            const proceedToNextStep = () => {
                if (this.currentVerseRepeat + 1 < targetRepeats) {
                    this.currentVerseRepeat++;
                    this.playCurrentVerse(true);
                    return;
                }

                this.currentVerseRepeat = 0;

                if (this.currentVerseIndex < this.verses.length - 1) {
                    this.currentVerseIndex++;
                    this.playCurrentVerse(true);
                } else if (this.loopMode === 'page' || targetRepeats > 1) {
                    this.currentVerseIndex = 0;
                    this.playCurrentVerse(true);
                } else {
                    this.isPlaying = false;
                }
            };

            if (this.pauseIntervalSec > 0) {
                this.startShadowingPause(this.pauseIntervalSec, proceedToNextStep);
            } else {
                proceedToNextStep();
            }
        },

        startShadowingPause(seconds, callback) {
            this.clearShadowingTimer();
            this.shadowingCountdown = seconds;
            this.pendingNextCallback = callback;

            this.shadowingTimer = setInterval(() => {
                this.shadowingCountdown--;
                if (this.shadowingCountdown <= 0) {
                    this.clearShadowingTimer();
                    if (this.isPlaying && typeof callback === 'function') {
                        callback();
                    }
                }
            }, 1000);
        },

        skipShadowingPause() {
            const cb = this.pendingNextCallback;
            this.clearShadowingTimer();
            if (typeof cb === 'function') {
                cb();
            }
        },

        clearShadowingTimer() {
            if (this.shadowingTimer) {
                clearInterval(this.shadowingTimer);
                this.shadowingTimer = null;
            }
            this.shadowingCountdown = 0;
            this.pendingNextCallback = null;
        },

        onReciterChange() {
            localStorage.setItem('hn_reciter_id', String(this.selectedReciterId));
            if (this.isPlaying) {
                this.playCurrentVerse(true);
            }
        },

        applyAudioSettings() {
            localStorage.setItem('hn_speed', String(this.playbackSpeed));
            if (this.audioEl) {
                this.audioEl.playbackRate = this.playbackSpeed;
            }
        },

        scrollToActiveVerse() {
            const el = document.getElementById(`verse-card-${this.currentVerseIndex}`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },

        setRecallMode(mode) {
            this.recallMode = mode;
            localStorage.setItem('hn_recall_mode', mode);
            this.clearAllPeeks();

            if (mode === 'pancingan_arti') {
                this.translationMode = 'show';
            }
        },

        recallModeLabel() {
            return {
                baca: 'Mode Baca',
                pancingan_awal: 'Pancingan Awal',
                pancingan_arti: 'Pancingan Arti',
                hafalan_penuh: 'Hafalan Penuh',
            }[this.recallMode] || 'Mode Baca';
        },

        isVersePeeked(verseKey) {
            return Boolean(this.peekedVerses[verseKey]);
        },

        isFirstWordsBlurred(verseKey) {
            if (this.isVersePeeked(verseKey)) return false;
            return this.recallMode === 'pancingan_arti' || this.recallMode === 'hafalan_penuh';
        },

        isRemainingWordsBlurred(verseKey) {
            if (this.isVersePeeked(verseKey)) return false;
            return this.recallMode === 'pancingan_awal'
                || this.recallMode === 'pancingan_arti'
                || this.recallMode === 'hafalan_penuh';
        },

        shouldShowTranslationBlock() {
            if (this.recallMode === 'pancingan_arti') return true;
            return this.translationMode !== 'hide';
        },

        isTranslationBlurred(verseKey) {
            if (this.peekedTranslations[verseKey] || this.isVersePeeked(verseKey)) return false;
            if (this.recallMode === 'pancingan_arti') return false;
            if (this.recallMode === 'hafalan_penuh') return true;
            return this.translationMode === 'peek';
        },

        handleVerseTap(verseKey) {
            if (this.recallMode === 'baca') return;
            this.togglePeekVerse(verseKey);
        },

        togglePeekVerse(verseKey) {
            this.peekedVerses[verseKey] = !this.peekedVerses[verseKey];
            if (!this.peekedVerses[verseKey]) {
                delete this.peekedVerses[verseKey];
            }
        },

        startHoldPeek(verseKey) {
            if (this.recallMode === 'baca') return;
            this.holdTimer = setTimeout(() => {
                this.peekedVerses[verseKey] = true;
            }, 180);
        },

        endHoldPeek() {
            if (this.holdTimer) {
                clearTimeout(this.holdTimer);
                this.holdTimer = null;
            }
        },

        togglePeekTranslation(verseKey) {
            if (this.translationMode === 'peek' || this.recallMode === 'hafalan_penuh') {
                this.peekedTranslations[verseKey] = !this.peekedTranslations[verseKey];
            }
        },

        clearAllPeeks() {
            this.peekedVerses = {};
            this.peekedTranslations = {};
        },

        async updatePageStatus(newStatus) {
            const prevStatus = this.pageStatus;
            this.pageStatus = newStatus;

            try {
                const response = await fetch(config.urls.progress, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ status: newStatus }),
                });

                const json = await response.json();
                if (response.ok && json.data) {
                    this.pageStatus = json.data.status;
                    this.reviewCount = json.data.review_count;
                    this.lastReviewedHuman = json.data.last_reviewed_human;
                    this.showToast(json.message);
                }
            } catch {
                this.pageStatus = prevStatus;
                this.showToast('Gagal menyimpan status halaman.');
            }
        },

        async logMurajaahToday() {
            this.isLoggingMurajaah = true;
            try {
                const response = await fetch(config.urls.murajaah, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                });

                const json = await response.json();
                if (response.ok && json.data) {
                    this.pageStatus = json.data.status;
                    this.reviewCount = json.data.review_count;
                    this.lastReviewedHuman = json.data.last_reviewed_human;
                    this.showToast(json.message);
                }
            } catch {
                this.showToast('Gagal mencatat muraja\'ah.');
            } finally {
                this.isLoggingMurajaah = false;
            }
        },

        async savePageNotes() {
            this.noteSaveState = 'saving';
            try {
                const response = await fetch(config.urls.notes, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({
                        narrative_summary: this.narrativeSummary,
                        verse_links_json: this.verseLinks,
                    }),
                });

                if (response.ok) {
                    this.noteSaveState = 'saved';
                } else {
                    this.noteSaveState = 'idle';
                }
            } catch {
                this.noteSaveState = 'idle';
            }
        },

        showToast(msg) {
            this.toastMessage = msg;
            setTimeout(() => {
                if (this.toastMessage === msg) {
                    this.toastMessage = '';
                }
            }, 3500);
        },

        toArabicNumerals(num) {
            const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            return String(num).replace(/[0-9]/g, (d) => arabicDigits[Number(d)]);
        },
    };
}
</script>
@endsection

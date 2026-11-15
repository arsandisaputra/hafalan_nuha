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
            progress: @js(route('pages.progress.update', ['pageNumber' => $pageNumber])),
            murajaah: @js(route('pages.murajaah.log', ['pageNumber' => $pageNumber])),
            notes: @js(route('pages.notes.upsert', ['pageNumber' => $pageNumber])),
        }
    })"
    x-init="initReader()"
    @keydown.window="handleGlobalShortcut($event)"
    :style="`--arabic-font-size: ${(fontSizePx / 16).toFixed(3)}rem;`"
    class="min-h-dvh flex flex-col pb-40"
>
    <!-- =========================================================================
         STICKY TOP BAR: Navigation, Juz/Page Metadata, Recall Modes & Controls
         ========================================================================= -->
    <header class="sticky top-0 z-30 border-b border-[#E5DEC9] dark:border-slate-800 bg-[#FAF8F5]/95 dark:bg-[#0F172A]/95 backdrop-blur-md shadow-sm">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 py-2.5 flex flex-col gap-2.5">
            <!-- Top Row: Brand, Page/Juz Header Info, Page Jumper, Dashboard & Theme -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <!-- Brand + Current Page & Juz Indicator -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard.index') }}"
                       class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-emerald-700 text-white font-semibold text-sm shadow-sm hover:bg-emerald-800 transition-colors"
                       aria-label="Ke Dashboard 30 Juz HafalanNuha">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>HafalanNuha</span>
                    </a>

                    <div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-800">
                                Juz {{ $positionMeta['juz_number'] }}
                            </span>
                            <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                                {{ $pageData['surah_names'] }}
                            </h1>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                            <span>{{ $positionMeta['page_label'] }}</span>
                            <span class="mx-1.5 text-slate-400">•</span>
                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">{{ $positionMeta['juz_position_label'] }}</span>
                        </p>
                    </div>
                </div>

                <!-- Right Actions: Page Selector, Prev/Next, Jumper Modal, Dashboard Grid, Theme Toggle -->
                <nav aria-label="Navigasi Halaman Mushaf" class="flex items-center gap-1.5 sm:gap-2">
                    @if ($positionMeta['prev_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['prev_page']]) }}"
                           class="interactive-pill inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold hover:border-emerald-500 transition-colors"
                           title="Halaman Sebelumnya (Halaman {{ $positionMeta['prev_page'] }})">
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="hidden sm:inline">Hal {{ $positionMeta['prev_page'] }}</span>
                        </a>
                    @endif

                    <!-- Direct Page Input Form -->
                    <form action="{{ route('home') }}" method="GET" class="inline-flex items-center" @submit.prevent="goToPageInput()">
                        <label for="quick-page-select" class="sr-only">Pilih Halaman (1 sampai 604)</label>
                        <input
                            id="quick-page-select"
                            type="number"
                            min="1"
                            max="604"
                            x-model.number="jumpPageInput"
                            @change="goToPageInput()"
                            class="w-16 text-center px-2 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-slate-100"
                        >
                    </form>

                    @if ($positionMeta['next_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['next_page']]) }}"
                           class="interactive-pill inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold hover:border-emerald-500 transition-colors"
                           title="Halaman Berikutnya (Halaman {{ $positionMeta['next_page'] }})">
                            <span class="hidden sm:inline">Hal {{ $positionMeta['next_page'] }}</span>
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endif

                    <!-- Open Juz/Page Jumper Modal Dialog -->
                    <button
                        type="button"
                        @click="$refs.juzJumperDialog.showModal()"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold hover:border-emerald-500 transition-colors"
                        aria-label="Pilih Juz atau Halaman">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        <span class="hidden md:inline">Pilih Juz</span>
                    </button>

                    <!-- Link to 30-Juz Dashboard -->
                    <a href="{{ route('dashboard.index') }}"
                       class="interactive-pill inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-emerald-600/30 bg-emerald-50/80 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 text-xs font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-900/70 transition-colors">
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span class="hidden sm:inline">Grid 30 Juz</span>
                    </a>

                    <!-- Dark Mode Toggle -->
                    <button
                        type="button"
                        @click="window.toggleColorScheme(); isDark = document.documentElement.classList.contains('dark')"
                        class="p-2 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:border-emerald-500 transition-colors"
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

            <!-- Second Row: 4 Active Recall Modes + Tadabbur/Anchor Toggles + Font Slider + Notes Drawer Button -->
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1.5 border-t border-[#E5DEC9]/70 dark:border-slate-800">
                <!-- Phase 3: 4 Self-Testing Active Recall Modes -->
                <div role="group" aria-label="Mode Uji Hafalan (Active Recall)" class="inline-flex flex-wrap items-center gap-1 p-1 rounded-xl bg-[#F3EFE6] dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700">
                    <button
                        type="button"
                        @click="setRecallMode('baca')"
                        :class="recallMode === 'baca'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all">
                        1. Mode Baca
                    </button>

                    <button
                        type="button"
                        @click="setRecallMode('pancingan_awal')"
                        :class="recallMode === 'pancingan_awal'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all"
                        title="Blur seluruh teks Arab kecuali 2–3 kata pertama di setiap ayat">
                        2. Pancingan Awal
                    </button>

                    <button
                        type="button"
                        @click="setRecallMode('pancingan_arti')"
                        :class="recallMode === 'pancingan_arti'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all"
                        title="Blur teks Arab dan tampilkan terjemahan Indonesia untuk mengetes ingatan ayat dari artinya">
                        3. Pancingan Arti
                    </button>

                    <button
                        type="button"
                        @click="setRecallMode('hafalan_penuh')"
                        :class="recallMode === 'hafalan_penuh'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-700 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all"
                        title="Uji hafalan penuh: blur seluruh teks Arab dan terjemahan">
                        4. Hafalan Penuh
                    </button>
                </div>

                <!-- Phase 2 Controls: Anchor Words Highlight, Translation Mode, Font Size Slider, Benang Merah Drawer -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Anchor Words Highlight Toggle -->
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

                    <!-- Translation Visibility Selector -->
                    <div class="inline-flex items-center gap-1 text-xs">
                        <label for="translation-mode-select" class="text-slate-600 dark:text-slate-400 font-medium hidden sm:inline">Arti:</label>
                        <select
                            id="translation-mode-select"
                            x-model="translationMode"
                            class="px-2 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <option value="show">Tampilkan Semua</option>
                            <option value="hide">Sembunyikan Semua</option>
                            <option value="peek">Sentuh / Hover</option>
                        </select>
                    </div>

                    <!-- Arabic Font Size Slider (28px - 38px) -->
                    <div class="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800">
                        <label for="arabic-font-slider" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Ukuran Arab</label>
                        <input
                            id="arabic-font-slider"
                            type="range"
                            min="28"
                            max="38"
                            step="1"
                            x-model.number="fontSizePx"
                            @input="saveFontPreference()"
                            class="w-20 cursor-pointer"
                        >
                        <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400 w-9 text-right" x-text="`${fontSizePx}px`"></span>
                    </div>

                    <!-- Mind-Link / Benang Merah Drawer Button -->
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
         MAIN WORKSPACE: Reading Canvas + Collapsible Mind-Link Drawer
         ========================================================================= -->
    <div class="max-w-6xl w-full mx-auto px-3 sm:px-6 pt-5 flex-1 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- CENTER COLUMN: Mushaf Page Reading Canvas -->
        <main
            id="mushaf-reading-canvas"
            :class="notesDrawerOpen ? 'lg:col-span-8' : 'lg:col-span-12 max-w-4xl mx-auto w-full'"
            class="transition-all duration-200 space-y-5"
        >
            <!-- Page Progress & Muraja'ah Status Bar -->
            <section aria-label="Status Hafalan dan Catatan Muraja'ah Halaman Ini"
                     class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700/80 shadow-sm flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status Halaman:</span>
                    <div role="group" aria-label="Pilih Status Hafalan Halaman" class="inline-flex rounded-xl p-0.5 bg-[#F3EFE6] dark:bg-slate-900 border border-[#E5DEC9] dark:border-slate-700">
                        <button
                            type="button"
                            @click="updatePageStatus('belum')"
                            :class="pageStatus === 'belum'
                                ? 'bg-slate-700 text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all">
                            Belum
                        </button>
                        <button
                            type="button"
                            @click="updatePageStatus('proses')"
                            :class="pageStatus === 'proses'
                                ? 'bg-amber-500 text-slate-950 shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all">
                            Proses
                        </button>
                        <button
                            type="button"
                            @click="updatePageStatus('hafal')"
                            :class="pageStatus === 'hafal'
                                ? 'bg-emerald-600 text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all">
                            Hafal
                        </button>
                    </div>
                </div>

                <!-- Muraja'ah Log Button & Counter -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <span class="font-semibold">Muraja'ah:</span>
                        <span class="font-bold text-emerald-700 dark:text-emerald-400" x-text="`${reviewCount}x`"></span>
                        <span class="mx-1 text-slate-400">•</span>
                        <span class="text-slate-500 dark:text-slate-400" x-text="`Terakhir: ${lastReviewedHuman}`"></span>
                    </div>

                    <button
                        type="button"
                        @click="logMurajaahToday()"
                        :disabled="isLoggingMurajaah"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-xs font-bold shadow-sm transition-all">
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="isLoggingMurajaah ? 'Menyimpan...' : 'Catat Muraja\'ah Hari Ini'"></span>
                    </button>
                </div>
            </section>

            <!-- Active Recall Mode Helper Banner (Shown when in a self-testing mode) -->
            <div x-show="recallMode !== 'baca'" x-cloak
                 class="p-3 rounded-xl bg-emerald-950/5 dark:bg-emerald-900/20 border border-emerald-600/30 flex items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2 text-emerald-900 dark:text-emerald-200">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>
                        <strong x-text="recallModeLabel()"></strong>:
                        Klik atau tahan pada ayat yang diblur untuk <em>Tap-to-Peek</em> (mengintip sementara), atau tekan ikon audio pada ayat.
                    </span>
                </div>
                <button
                    type="button"
                    @click="clearAllPeeks()"
                    x-show="Object.keys(peekedVerses).length > 0"
                    class="px-2.5 py-1 rounded-lg bg-emerald-700 text-white font-semibold shrink-0">
                    Tutup Intipan
                </button>
            </div>

            <!-- Toast Notification Feedback -->
            <div x-show="toastMessage !== ''" x-cloak
                 x-transition.opacity
                 role="status"
                 aria-live="polite"
                 class="p-3 rounded-xl bg-emerald-800 text-white text-xs font-semibold shadow-md flex items-center justify-between">
                <span x-text="toastMessage"></span>
                <button type="button" @click="toastMessage = ''" class="ml-3 text-emerald-200 hover:text-white">✕</button>
            </div>

            <!-- Mushaf Page Frame (Madinah Mushaf Inspired Paper Frame) -->
            <article class="rounded-3xl bg-[#FAF8F5] dark:bg-[#131C31] border-2 border-[#E5DEC9] dark:border-slate-700/90 shadow-md overflow-hidden">
                <!-- Mushaf Frame Top Ornament Header -->
                <div class="px-5 py-3 bg-[#F3EFE6] dark:bg-slate-800/90 border-b border-[#E5DEC9] dark:border-slate-700 flex items-center justify-between text-xs font-semibold text-slate-700 dark:text-slate-300">
                    <span>Juz {{ $positionMeta['juz_number'] }} • {{ $positionMeta['juz_position_label'] }}</span>
                    <span class="font-bold text-emerald-800 dark:text-emerald-300">{{ $pageData['surah_names'] }}</span>
                    <span>Halaman {{ $pageNumber }} / 604</span>
                </div>

                @if (! empty($pageData['is_fallback']) && empty($pageData['verses']))
                    <!-- Offline Uncached Notice -->
                    <div class="p-8 text-center space-y-3">
                        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">Halaman Belum Tersimpan di Cache Lokal</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 max-w-lg mx-auto">
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
                    <div class="divide-y divide-[#E5DEC9]/70 dark:divide-slate-800/80">
                        <template x-for="(verse, idx) in verses" :key="verse.verse_key">
                            <div
                                :id="`verse-card-${idx}`"
                                :class="{
                                    'bg-emerald-50/90 dark:bg-emerald-950/35 ring-2 ring-inset ring-emerald-500/60': currentVerseIndex === idx && isPlaying,
                                    'bg-amber-50/50 dark:bg-amber-950/20': currentVerseIndex === idx && !isPlaying
                                }"
                                class="p-4 sm:p-6 transition-colors duration-200 group"
                            >
                                <!-- Surah Banner Header if Verse 1 -->
                                <template x-if="verse.is_surah_start">
                                    <div class="mb-5 rounded-2xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-teal-900 text-white p-4 text-center shadow-sm border border-emerald-600/30">
                                        <div class="flex items-center justify-between text-xs text-emerald-200 font-medium">
                                            <span x-text="`Surah ke-${verse.chapter_id}`"></span>
                                            <span x-text="verse.surah_name_id"></span>
                                        </div>
                                        <h2 class="text-lg sm:text-xl font-bold tracking-wide mt-0.5">
                                            <span x-text="`Surah ${verse.surah_name}`"></span>
                                            <span class="font-arabic text-2xl ml-2" dir="rtl" x-text="verse.surah_name_arabic"></span>
                                        </h2>
                                        <template x-if="verse.show_bismillah">
                                            <p class="arabic-uthmani text-center text-emerald-100 mt-2 text-2xl" dir="rtl">
                                                بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ
                                            </p>
                                        </template>
                                    </div>
                                </template>

                                <!-- Verse Card Toolbar: Verse Badge, Play Verse Audio Prompt, Repeat Counter, Peek Button -->
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-2">
                                        <!-- Verse Key Pill -->
                                        <span
                                            :class="currentVerseIndex === idx
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-[#F3EFE6] dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-[#E5DEC9] dark:border-slate-700'"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-bold"
                                            x-text="verse.verse_key"
                                        ></span>

                                        <!-- Play Single Verse Audio Prompt Button -->
                                        <button
                                            type="button"
                                            @click="playSingleVerse(idx)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900 text-xs font-semibold border border-emerald-500/30 transition-colors"
                                            :aria-label="`Putar Audio Ayat ${verse.verse_key}`">
                                            <svg x-show="!(currentVerseIndex === idx && isPlaying)" class="w-3.5 h-3.5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                                            </svg>
                                            <svg x-show="currentVerseIndex === idx && isPlaying" x-cloak class="w-3.5 h-3.5 animate-pulse" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zm7 0a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" clip-rule="evenodd" />
                                            </svg>
                                            <span x-text="currentVerseIndex === idx && isPlaying ? 'Sedang Diputar' : 'Putar Ayat'"></span>
                                        </button>

                                        <!-- Active Repeat Indicator -->
                                        <span
                                            x-show="currentVerseIndex === idx && isPlaying && maxVerseRepeats() > 1"
                                            x-cloak
                                            class="px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-800 dark:text-amber-300 text-[11px] font-bold"
                                            x-text="`Ulangan ${currentVerseRepeat + 1}/${maxVerseRepeats()}`"
                                        ></span>
                                    </div>

                                    <!-- Right Verse Controls: Tap-to-Peek Status & Personal Cue Pill -->
                                    <div class="flex items-center gap-2">
                                        <template x-if="verseLinks[verse.verse_key]">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-100/80 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200 text-xs font-medium border border-amber-300/60 dark:border-amber-800/60"
                                                  x-text="`🔗 ${verseLinks[verse.verse_key]}`">
                                            </span>
                                        </template>

                                        <button
                                            type="button"
                                            x-show="recallMode !== 'baca'"
                                            @click="togglePeekVerse(verse.verse_key)"
                                            :class="isVersePeeked(verse.verse_key)
                                                ? 'bg-amber-500 text-slate-950'
                                                : 'bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors">
                                            <span x-text="isVersePeeked(verse.verse_key) ? 'Sembunyikan' : 'Intip Ayat'"></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- ARABIC UTHMANI VERSE BLOCK (RTL, Generous Line-Height, Tap-to-Peek & Anchor Words) -->
                                <div
                                    dir="rtl"
                                    lang="ar"
                                    @click="handleVerseTap(verse.verse_key)"
                                    @mousedown="startHoldPeek(verse.verse_key)"
                                    @mouseup="endHoldPeek(verse.verse_key)"
                                    @touchstart.passive="startHoldPeek(verse.verse_key)"
                                    @touchend.passive="endHoldPeek(verse.verse_key)"
                                    :class="recallMode !== 'baca' ? 'cursor-pointer select-none' : ''"
                                    class="arabic-uthmani py-2 px-1 rounded-xl text-slate-900 dark:text-slate-100"
                                    :title="recallMode !== 'baca' ? 'Klik atau tahan untuk mengintip ayat ini (Tap-to-Peek)' : ''"
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

                                    <!-- Ornate End of Ayah Marker -->
                                    <span class="inline-flex items-center justify-center mx-1.5 px-2 py-0.5 rounded-full text-base font-bold bg-emerald-100/90 dark:bg-emerald-950/90 text-emerald-800 dark:text-emerald-300 border border-emerald-500/40 align-middle">
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
                                    class="mt-3 pt-2.5 border-t border-[#E5DEC9]/50 dark:border-slate-800 text-sm sm:text-base text-slate-700 dark:text-slate-300 leading-relaxed"
                                >
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400 mr-1" x-text="`${verse.verse_number}.`"></span>
                                    <span x-text="verse.translation_id"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                <!-- Mushaf Frame Bottom Footer Navigation -->
                <div class="px-5 py-3.5 bg-[#F3EFE6] dark:bg-slate-800/90 border-t border-[#E5DEC9] dark:border-slate-700 flex items-center justify-between">
                    @if ($positionMeta['prev_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['prev_page']]) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-[#E5DEC9] dark:border-slate-700 text-xs font-bold hover:border-emerald-500 transition-colors">
                            ← Halaman {{ $positionMeta['prev_page'] }}
                        </a>
                    @else
                        <span></span>
                    @endif

                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                        {{ $positionMeta['page_label'] }} • Juz {{ $positionMeta['juz_number'] }}
                    </span>

                    @if ($positionMeta['next_page'])
                        <a href="{{ route('mushaf.show', ['pageNumber' => $positionMeta['next_page']]) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                            Halaman {{ $positionMeta['next_page'] }} →
                        </a>
                    @else
                        <span></span>
                    @endif
                </div>
            </article>
        </main>

        <!-- =====================================================================
             RIGHT SIDEBAR / COLLAPSIBLE DRAWER: Phase 2 Mind-Link ("Benang Merah")
             ===================================================================== -->
        <aside
            x-show="notesDrawerOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            aria-label="Panel Catatan Benang Merah dan Tadabbur Halaman"
            class="lg:col-span-4 lg:sticky lg:top-28 rounded-2xl bg-white dark:bg-slate-800/95 border border-[#E5DEC9] dark:border-slate-700 shadow-lg p-4 sm:p-5 space-y-4"
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
                    <!-- Auto-Save Status Indicator -->
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md"
                          :class="{
                              'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': noteSaveState === 'saving',
                              'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': noteSaveState === 'saved',
                              'bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-400': noteSaveState === 'idle'
                          }"
                          x-text="noteSaveState === 'saving' ? 'Menyimpan...' : (noteSaveState === 'saved' ? 'Tersimpan ✓' : 'Auto-save aktif')">
                    </span>

                    <button
                        type="button"
                        @click="notesDrawerOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                        aria-label="Tutup Panel Benang Merah">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Narrative Summary ("Benang Merah" Storyline of the Page) -->
            <div class="space-y-1.5">
                <label for="page-narrative-textarea" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    Alur Cerita / Koneksi Antar-Ayat di Halaman Ini:
                </label>
                <textarea
                    id="page-narrative-textarea"
                    rows="6"
                    x-model="narrativeSummary"
                    @input.debounce.800ms="savePageNotes()"
                    @blur="savePageNotes()"
                    placeholder="Contoh: Ayat 1-3: Sifat orang bertakwa -> Ayat 4-5: Balasan mereka -> Ayat 6-7: Kontras orang kafir..."
                    class="w-full rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-900/90 p-3 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 leading-relaxed resize-y"
                ></textarea>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Tersimpan otomatis saat Anda berhenti mengetik atau berpindah kolom.
                </p>
            </div>

            <!-- Per-Verse Anchor Cues (verse_links_json) -->
            <div class="space-y-2 pt-2 border-t border-[#E5DEC9] dark:border-slate-700">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    Kata Kunci / Jembatan Memori Per Ayat:
                </h3>
                <div class="max-h-64 overflow-y-auto pr-1 space-y-2">
                    <template x-for="verse in verses" :key="`note-${verse.verse_key}`">
                        <div class="flex items-center gap-2">
                            <label :for="`cue-${verse.verse_key}`"
                                   class="w-12 shrink-0 text-center py-1 rounded-md bg-[#F3EFE6] dark:bg-slate-900 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                                   x-text="verse.verse_key">
                            </label>
                            <input
                                :id="`cue-${verse.verse_key}`"
                                type="text"
                                x-model="verseLinks[verse.verse_key]"
                                @input.debounce.800ms="savePageNotes()"
                                @blur="savePageNotes()"
                                :placeholder="`Kata kunci ayat ${verse.verse_number}...`"
                                class="flex-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-900 px-2.5 py-1 text-xs text-slate-800 dark:text-slate-200"
                            >
                        </div>
                    </template>
                </div>
            </div>
        </aside>
    </div>

    <!-- =========================================================================
         STICKY BOTTOM AUDIO CONTROLLER BAR: Phase 1 Seamless Page Audio Engine
         ========================================================================= -->
    <footer aria-label="Pemutar Audio Murottal Halaman"
            class="fixed bottom-0 inset-x-0 z-40 border-t border-[#E5DEC9] dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md shadow-2xl">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 py-2.5 flex flex-col gap-2">
            <!-- Audio Progress Scrub Bar & Time Indicator -->
            <div class="flex items-center gap-2.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                <span class="w-9 text-right tabular-nums" x-text="formatTime(audioCurrentTime)">0:00</span>
                <input
                    type="range"
                    min="0"
                    :max="audioDuration > 0 ? audioDuration : 100"
                    step="0.1"
                    :value="audioCurrentTime"
                    @input="seekAudio($event.target.value)"
                    aria-label="Posisi Audio Ayat"
                    class="flex-1 h-1.5 rounded-lg cursor-pointer accent-emerald-600"
                >
                <span class="w-9 tabular-nums" x-text="formatTime(audioDuration)">0:00</span>
            </div>

            <!-- Shadowing Countdown Banner (When waiting between verses) -->
            <div x-show="shadowingCountdown > 0" x-cloak
                 class="px-3 py-1 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-900 dark:text-amber-200 text-xs font-semibold flex items-center justify-between">
                <span>🎙️ Waktu tirukan / ulangi bacaan ayat (<strong x-text="`${shadowingCountdown} detik`"></strong>)...</span>
                <button type="button" @click="skipShadowingPause()" class="underline font-bold">Lanjut Sekarang →</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-4">
                <!-- Left: Transport Controls (Prev, Play/Pause, Next) & Active Verse Info -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        @click="prevVerse()"
                        :disabled="verses.length === 0"
                        class="p-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 hover:border-emerald-500 disabled:opacity-40"
                        aria-label="Ayat Sebelumnya">
                        <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        @click="togglePlayPause()"
                        :disabled="verses.length === 0"
                        class="px-4 py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md inline-flex items-center gap-2 transition-all">
                        <svg x-show="!isPlaying" class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                        </svg>
                        <svg x-show="isPlaying" x-cloak class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zm7 0a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" clip-rule="evenodd" />
                        </svg>
                        <span x-text="isPlaying ? 'Jeda' : 'Putar Halaman'"></span>
                    </button>

                    <button
                        type="button"
                        @click="nextVerse()"
                        :disabled="verses.length === 0"
                        class="p-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 hover:border-emerald-500 disabled:opacity-40"
                        aria-label="Ayat Berikutnya">
                        <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798L4.555 5.168z" />
                        </svg>
                    </button>

                    <!-- Active Verse Indicator -->
                    <div class="text-xs">
                        <div class="font-bold text-slate-900 dark:text-slate-100">
                            <span x-text="activeVerse() ? `Ayat ${activeVerse().verse_key}` : 'Siap Diputar'"></span>
                            <span class="text-slate-400 font-normal" x-text="`(${currentVerseIndex + 1}/${verses.length})`"></span>
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 hidden sm:block" x-text="activeVerse() ? activeVerse().surah_name : ''"></div>
                    </div>
                </div>

                <!-- Right: Loop Mode, Qari Selector, Playback Speed, and Shadowing Pause Interval -->
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <!-- Loop Mode Selector (Page Loop / Verse 1x, 3x, 5x) -->
                    <div class="inline-flex items-center gap-1">
                        <label for="loop-mode-select" class="text-slate-500 dark:text-slate-400 font-medium hidden md:inline">Loop:</label>
                        <select
                            id="loop-mode-select"
                            x-model="loopMode"
                            class="px-2.5 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-semibold">
                            <option value="page">🔁 Loop Halaman</option>
                            <option value="verse_3">🔂 Ulang Ayat 3x</option>
                            <option value="verse_5">🔂 Ulang Ayat 5x</option>
                            <option value="verse_1">➡️ Sekali Jalan (1x)</option>
                        </select>
                    </div>

                    <!-- Qari / Reciter Selector -->
                    <div class="inline-flex items-center gap-1">
                        <label for="reciter-select" class="text-slate-500 dark:text-slate-400 font-medium hidden md:inline">Qari:</label>
                        <select
                            id="reciter-select"
                            x-model.number="selectedReciterId"
                            @change="onReciterChange()"
                            class="px-2.5 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-semibold max-w-[180px] truncate">
                            @foreach ($reciters as $id => $qari)
                                <option value="{{ $id }}">{{ $qari['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Playback Speed Selector (0.75x, 1.0x, 1.25x, 1.5x) -->
                    <div class="inline-flex items-center gap-1">
                        <label for="playback-speed-select" class="sr-only">Kecepatan Audio</label>
                        <select
                            id="playback-speed-select"
                            x-model.number="playbackSpeed"
                            @change="applyAudioSettings()"
                            class="px-2 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-bold text-emerald-700 dark:text-emerald-400">
                            <option value="0.75">0.75x</option>
                            <option value="1">1.0x</option>
                            <option value="1.25">1.25x</option>
                            <option value="1.5">1.5x</option>
                        </select>
                    </div>

                    <!-- Shadowing Pause Interval (0s, 2s, 5s) -->
                    <div class="inline-flex items-center gap-1" title="Jeda antar-ayat untuk menirukan bacaan Qari (Shadowing)">
                        <label for="pause-interval-select" class="text-slate-500 dark:text-slate-400 font-medium hidden sm:inline">Jeda:</label>
                        <select
                            id="pause-interval-select"
                            x-model.number="pauseIntervalSec"
                            class="px-2 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-[#FAF8F5] dark:bg-slate-800 font-semibold">
                            <option value="0">Jeda 0d</option>
                            <option value="2">Jeda 2d</option>
                            <option value="5">Jeda 5d</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- =========================================================================
         NATIVE MODAL DIALOG (<dialog closedby="any">): Quick Juz & Page Jumper
         ========================================================================= -->
    <dialog
        x-ref="juzJumperDialog"
        closedby="any"
        class="rounded-3xl bg-[#FAF8F5] dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-[#E5DEC9] dark:border-slate-700 shadow-2xl p-0 w-full max-w-2xl"
    >
        <div class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-800 pb-3">
                <div>
                    <h2 class="text-base font-bold">Navigasi Cepat 30 Juz Mushaf Madinah</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Standar 15 Baris (20 Halaman per Juz • 604 Halaman)</p>
                </div>
                <form method="dialog">
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-xs font-bold">
                        Tutup (Esc)
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

        // UI & Theme State
        isDark: document.documentElement.classList.contains('dark'),
        fontSizePx: Number(localStorage.getItem('hn_font_px') || 32),
        notesDrawerOpen: window.innerWidth >= 1024 && (config.initialNarrative || '').length > 0,
        toastMessage: '',

        // Phase 2 & Phase 3 State
        recallMode: localStorage.getItem('hn_recall_mode') || 'baca', // 'baca' | 'pancingan_awal' | 'pancingan_arti' | 'hafalan_penuh'
        translationMode: localStorage.getItem('hn_translation_mode') || 'show', // 'show' | 'hide' | 'peek'
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
        noteSaveState: 'idle', // 'idle' | 'saving' | 'saved'

        // Phase 1: Seamless Audio Engine State
        audioEl: null,
        isPlaying: false,
        audioCurrentTime: 0,
        audioDuration: 0,
        currentVerseIndex: 0,
        currentVerseRepeat: 0,
        loopMode: localStorage.getItem('hn_loop_mode') || 'page', // 'page' | 'verse_1' | 'verse_3' | 'verse_5'
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

        // =====================================================================
        // PHASE 1: SEAMLESS PAGE AUDIO PLAYER & LOOP ENGINE
        // =====================================================================
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
                    // Loop back to the first verse on the page
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

        // =====================================================================
        // PHASE 3: ACTIVE RECALL MODES & TAP-TO-PEEK LOGIC
        // =====================================================================
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
                baca: 'Mode Baca Normal',
                pancingan_awal: 'Mode Pancingan Awal (2–3 Kata Pertama)',
                pancingan_arti: 'Mode Pancingan Arti (Tebak Ayat dari Terjemahan)',
                hafalan_penuh: 'Mode Hafalan Penuh (Uji Kosong)',
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

        // =====================================================================
        // PHASE 2 & 4: AJAX / FETCH FOR PROGRESS, MURAJA'AH, AND PAGE NOTES
        // =====================================================================
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

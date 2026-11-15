@extends('layouts.app')

@section('title', 'Dashboard Progress 30 Juz & Muraja\'ah | HafalanNuha')

@section('content')
<div
    x-data="dashboardOverviewApp({
        juzGrid: @js($juzGrid),
        summary: @js($summary),
        stalePages: @js($stalePages),
        csrfToken: @js(csrf_token()),
    })"
    class="min-h-dvh flex flex-col pb-20"
>
    <!-- =========================================================================
         STICKY HEADER: Brand, Quick Page Search & Reader Shortcut
         ========================================================================= -->
    <header class="sticky top-0 z-30 border-b border-[#E5DEC9] dark:border-slate-800 bg-[#FAF8F5]/95 dark:bg-[#0F172A]/95 backdrop-blur-md shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('mushaf.show', ['pageNumber' => 1]) }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-700 text-white font-bold text-sm shadow-sm hover:bg-emerald-800 transition-colors">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>HafalanNuha</span>
                </a>
                <div>
                    <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100">
                        Dashboard Tahfidz & Muraja'ah 30 Juz
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Metodologi Mushaf Madinah 15 Baris (20 Halaman per Juz • 604 Halaman)
                    </p>
                </div>
            </div>

            <nav aria-label="Aksi Utama Dashboard" class="flex items-center gap-2">
                <form @submit.prevent="openPage(quickPage)" class="inline-flex items-center gap-1.5">
                    <label for="dashboard-page-jump" class="text-xs font-semibold text-slate-600 dark:text-slate-400 hidden sm:inline">Ke Halaman:</label>
                    <input
                        id="dashboard-page-jump"
                        type="number"
                        min="1"
                        max="604"
                        x-model.number="quickPage"
                        placeholder="1-604"
                        class="w-20 px-2.5 py-1.5 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold text-center"
                    >
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors">
                        Buka Mushaf
                    </button>
                </form>

                <!-- Dark Mode Toggle -->
                <button
                    type="button"
                    @click="window.toggleColorScheme(); isDark = document.documentElement.classList.contains('dark')"
                    class="p-2 rounded-xl border border-[#E5DEC9] dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:border-emerald-500 transition-colors"
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
    </header>

    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 pt-6 space-y-6">
        <!-- =====================================================================
             SUMMARY KPI CARDS
             ===================================================================== -->
        <section aria-label="Ringkasan Capaian Tahfidz" class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Halaman Hafal</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" x-text="summary.hafal_count"></span>
                    <span class="text-xs text-slate-500" x-text="`/ 604 (${summary.hafal_percent}%)`"></span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Sedang Proses</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-amber-500" x-text="summary.proses_count"></span>
                    <span class="text-xs text-slate-500">halaman</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Belum Dimulai</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-700 dark:text-slate-300" x-text="summary.belum_count"></span>
                    <span class="text-xs text-slate-500">halaman</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-rose-500/40 shadow-sm">
                <div class="text-xs font-semibold text-rose-600 dark:text-rose-400">Perlu Muraja'ah (&gt;7 Hari)</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-rose-600 dark:text-rose-400" x-text="summary.needs_murajaah_count"></span>
                    <span class="text-xs text-slate-500">halaman</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700 shadow-sm col-span-2 md:col-span-1">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Sesi Muraja'ah</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-teal-600 dark:text-teal-400" x-text="`${summary.total_reviews}x`"></span>
                    <span class="text-xs text-slate-500" x-text="`• ${summary.notes_count} catatan`"></span>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             PRIORITY MURAJA'AH ALERT SECTION (Pages not reviewed in > 7 days)
             ===================================================================== -->
        <template x-if="stalePages.length > 0">
            <section aria-label="Daftar Halaman Prioritas Muraja'ah Lebih dari 7 Hari"
                     class="p-4 sm:p-5 rounded-2xl bg-rose-50/90 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800/70 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-bold">!</span>
                        <h2 class="text-sm font-bold text-rose-950 dark:text-rose-200">
                            Prioritas Muraja'ah: Halaman Belum Diulang &gt; 7 Hari
                        </h2>
                    </div>
                    <span class="text-xs text-rose-800 dark:text-rose-300 font-medium">
                        Klik "Catat Muraja'ah" setelah selesai mengulang halaman.
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    <template x-for="item in stalePages" :key="`stale-${item.page_number}`">
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900/60 flex items-center justify-between gap-2 shadow-sm">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <a :href="`/mushaf/${item.page_number}`"
                                       class="text-xs font-bold text-slate-900 dark:text-slate-100 hover:text-emerald-600 underline"
                                       x-text="`Halaman ${item.page_number} (Juz ${item.juz_number})`">
                                    </a>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                          x-text="item.status"></span>
                                </div>
                                <p class="text-[11px] text-rose-600 dark:text-rose-400 font-medium mt-0.5"
                                   x-text="`Terakhir: ${item.last_reviewed_human} (${item.review_count}x)`">
                                </p>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    @click="quickLogMurajaah(item.page_number)"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors">
                                    + Muraja'ah
                                </button>
                                <a :href="`/mushaf/${item.page_number}`"
                                   class="px-2 py-1 rounded-lg border border-[#E5DEC9] dark:border-slate-700 text-xs font-semibold hover:border-emerald-500">
                                    Buka
                                </a>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </template>

        <!-- =====================================================================
             LEGEND & FILTER BAR FOR THE 30-JUZ VISUAL GRID
             ===================================================================== -->
        <section aria-label="Filter dan Keterangan Warna Grid 30 Juz"
                 class="p-4 rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
            <!-- Color Legend -->
            <div class="flex flex-wrap items-center gap-4 text-xs font-semibold">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-emerald-600 inline-block"></span>
                    <span>Hafal</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-amber-400 inline-block"></span>
                    <span>Proses Menghafal</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-[#EFECE6] dark:bg-slate-700 border border-[#D5CCB8] dark:border-slate-600 inline-block"></span>
                    <span>Belum Dimulai</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-emerald-600 ring-2 ring-rose-500 ring-offset-1 inline-block"></span>
                    <span class="text-rose-600 dark:text-rose-400 font-bold">Belum Muraja'ah &gt; 7 Hari</span>
                </span>
            </div>

            <!-- Filter Mode -->
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-500 font-medium">Filter Juz:</span>
                <button
                    type="button"
                    @click="filterMode = 'all'"
                    :class="filterMode === 'all' ? 'bg-slate-900 text-white dark:bg-emerald-600' : 'bg-[#F3EFE6] dark:bg-slate-900 text-slate-700 dark:text-slate-300'"
                    class="px-3 py-1 rounded-lg font-semibold transition-colors">
                    Semua (30 Juz)
                </button>
                <button
                    type="button"
                    @click="filterMode = 'active'"
                    :class="filterMode === 'active' ? 'bg-slate-900 text-white dark:bg-emerald-600' : 'bg-[#F3EFE6] dark:bg-slate-900 text-slate-700 dark:text-slate-300'"
                    class="px-3 py-1 rounded-lg font-semibold transition-colors">
                    Ada Progress
                </button>
                <button
                    type="button"
                    @click="filterMode = 'stale'"
                    :class="filterMode === 'stale' ? 'bg-rose-600 text-white' : 'bg-[#F3EFE6] dark:bg-slate-900 text-slate-700 dark:text-slate-300'"
                    class="px-3 py-1 rounded-lg font-semibold transition-colors">
                    Perlu Muraja'ah (&gt;7 Hari)
                </button>
            </div>
        </section>

        <!-- Toast Feedback -->
        <div x-show="toastMessage !== ''" x-cloak
             role="status"
             aria-live="polite"
             class="fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-xl bg-emerald-800 text-white text-xs font-bold shadow-xl">
            <span x-text="toastMessage"></span>
        </div>

        <!-- =====================================================================
             30-JUZ x 20-PAGE VISUAL GRID OVERVIEW
             ===================================================================== -->
        <section aria-label="Peta Visual 30 Juz Mushaf Madinah" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="juz in filteredJuzGrid()" :key="`juz-card-${juz.juz_number}`">
                <article class="rounded-2xl bg-white dark:bg-slate-800/90 border border-[#E5DEC9] dark:border-slate-700/90 p-4 shadow-sm flex flex-col justify-between space-y-3">
                    <!-- Juz Card Header -->
                    <div class="flex items-center justify-between border-b border-[#E5DEC9]/70 dark:border-slate-700 pb-2.5">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100" x-text="`Juz ${juz.juz_number}`"></h2>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400"
                                      x-text="`Hal. ${juz.start_page}–${juz.end_page} (${juz.total_pages} hal)`"></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-xs font-bold">
                            <span x-show="juz.stale_count > 0"
                                  class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 text-[11px]"
                                  x-text="`${juz.stale_count} perlu muraja'ah`"></span>
                            <span class="text-emerald-700 dark:text-emerald-400" x-text="`${juz.hafal_count}/${juz.total_pages} Hafal`"></span>
                        </div>
                    </div>

                    <!-- Progress Bar for the Juz -->
                    <div class="w-full h-1.5 rounded-full bg-[#EFECE6] dark:bg-slate-700 overflow-hidden">
                        <div class="h-full bg-emerald-600 transition-all duration-300"
                             :style="`width: ${juz.completion_percent}%`"></div>
                    </div>

                    <!-- 20 Page Blocks Grid for this Juz -->
                    <div class="grid grid-cols-10 gap-1.5 pt-1">
                        <template x-for="pageItem in juz.pages" :key="`p-${pageItem.page_number}`">
                            <button
                                type="button"
                                @click="selectPageForModal(pageItem, juz.juz_number)"
                                :class="{
                                    'bg-emerald-600 text-white hover:bg-emerald-700': pageItem.status === 'hafal',
                                    'bg-amber-400 text-slate-950 hover:bg-amber-500': pageItem.status === 'proses',
                                    'bg-[#EFECE6] dark:bg-slate-700/80 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-[#D5CCB8]/70 dark:border-slate-600': pageItem.status === 'belum',
                                    'ring-2 ring-rose-500 ring-offset-1 dark:ring-offset-slate-900 animate-pulse': pageItem.needs_murajaah
                                }"
                                class="relative h-8 rounded-lg text-[11px] font-bold flex items-center justify-center transition-transform hover:scale-105"
                                :title="`Halaman ${pageItem.page_number} (Hal ${pageItem.page_in_juz} di Juz ${juz.juz_number}) • Status: ${pageItem.status.toUpperCase()} • Muraja'ah: ${pageItem.review_count}x`"
                            >
                                <span x-text="pageItem.page_number"></span>
                                <!-- Small dot if page has Benang Merah notes -->
                                <span x-show="pageItem.has_notes"
                                      class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                            </button>
                        </template>
                    </div>
                </article>
            </template>
        </section>
    </main>

    <!-- =========================================================================
         QUICK PAGE INSPECTOR & ACTION DIALOG (<dialog closedby="any">)
         ========================================================================= -->
    <dialog
        x-ref="pageActionDialog"
        closedby="any"
        class="rounded-3xl bg-[#FAF8F5] dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-[#E5DEC9] dark:border-slate-700 shadow-2xl p-0 w-full max-w-md"
    >
        <template x-if="selectedPage">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-[#E5DEC9] dark:border-slate-800 pb-3">
                    <div>
                        <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 text-xs font-bold"
                              x-text="`Juz ${selectedPageJuz}`"></span>
                        <h3 class="text-base font-bold mt-1" x-text="`Halaman ${selectedPage.page_number} / 604`"></h3>
                    </div>
                    <form method="dialog">
                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-xs font-bold">✕</button>
                    </form>
                </div>

                <!-- Status Switcher -->
                <div class="space-y-1.5">
                    <div class="text-xs font-bold text-slate-600 dark:text-slate-400">Ubah Status Hafalan:</div>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            @click="updateGridPageStatus(selectedPage, 'belum')"
                            :class="selectedPage.status === 'belum' ? 'bg-slate-800 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl text-xs font-bold">
                            Belum
                        </button>
                        <button
                            type="button"
                            @click="updateGridPageStatus(selectedPage, 'proses')"
                            :class="selectedPage.status === 'proses' ? 'bg-amber-500 text-slate-950' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl text-xs font-bold">
                            Proses
                        </button>
                        <button
                            type="button"
                            @click="updateGridPageStatus(selectedPage, 'hafal')"
                            :class="selectedPage.status === 'hafal' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700'"
                            class="py-2 rounded-xl text-xs font-bold">
                            Hafal
                        </button>
                    </div>
                </div>

                <!-- Muraja'ah Info & Log Button -->
                <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-[#E5DEC9] dark:border-slate-700 flex items-center justify-between">
                    <div class="text-xs">
                        <div class="font-bold" x-text="`Total Muraja'ah: ${selectedPage.review_count}x`"></div>
                        <div class="text-slate-500 dark:text-slate-400"
                             x-text="`Terakhir: ${selectedPage.last_reviewed_human || 'Belum pernah'}`"></div>
                    </div>
                    <button
                        type="button"
                        @click="quickLogMurajaah(selectedPage.page_number)"
                        class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                        + Catat Muraja'ah
                    </button>
                </div>

                <!-- Open Mushaf Reader Link -->
                <div class="pt-1 flex justify-end">
                    <a :href="`/mushaf/${selectedPage.page_number}`"
                       class="w-full text-center py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-sm">
                        Buka Halaman di Mushaf Reader →
                    </a>
                </div>
            </div>
        </template>
    </dialog>
</div>

<script>
function dashboardOverviewApp(config) {
    return {
        juzGrid: config.juzGrid || [],
        summary: config.summary || {},
        stalePages: config.stalePages || [],
        filterMode: 'all', // 'all' | 'active' | 'stale'
        quickPage: 1,
        selectedPage: null,
        selectedPageJuz: 1,
        toastMessage: '',
        isDark: document.documentElement.classList.contains('dark'),

        filteredJuzGrid() {
            if (this.filterMode === 'active') {
                return this.juzGrid.filter((j) => j.hafal_count > 0 || j.proses_count > 0);
            }
            if (this.filterMode === 'stale') {
                return this.juzGrid.filter((j) => j.stale_count > 0);
            }
            return this.juzGrid;
        },

        openPage(page) {
            const target = Math.max(1, Math.min(604, Number(page) || 1));
            window.location.href = `/mushaf/${target}`;
        },

        selectPageForModal(pageItem, juzNumber) {
            this.selectedPage = pageItem;
            this.selectedPageJuz = juzNumber;
            this.$refs.pageActionDialog.showModal();
        },

        findPageObject(pageNumber) {
            for (const juz of this.juzGrid) {
                for (const p of juz.pages) {
                    if (p.page_number === pageNumber) {
                        return { page: p, juz };
                    }
                }
            }
            return null;
        },

        async updateGridPageStatus(pageItem, newStatus) {
            try {
                const response = await fetch(`/api/pages/${pageItem.page_number}/progress`, {
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
                    pageItem.status = json.data.status;
                    pageItem.review_count = json.data.review_count;
                    pageItem.last_reviewed_human = json.data.last_reviewed_human;
                    pageItem.needs_murajaah = json.data.needs_murajaah;
                    this.recalculateSummaryCounts();
                    this.showToast(json.message);
                }
            } catch {
                this.showToast('Gagal memperbarui status halaman.');
            }
        },

        async quickLogMurajaah(pageNumber) {
            try {
                const response = await fetch(`/api/pages/${pageNumber}/murajaah`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                });

                const json = await response.json();
                if (response.ok && json.data) {
                    const found = this.findPageObject(pageNumber);
                    if (found) {
                        found.page.status = json.data.status;
                        found.page.review_count = json.data.review_count;
                        found.page.last_reviewed_human = json.data.last_reviewed_human;
                        found.page.needs_murajaah = false;
                    }
                    this.stalePages = this.stalePages.filter((s) => s.page_number !== pageNumber);
                    this.recalculateSummaryCounts();
                    this.showToast(json.message);
                }
            } catch {
                this.showToast('Gagal mencatat muraja\'ah.');
            }
        },

        recalculateSummaryCounts() {
            let hafal = 0;
            let proses = 0;
            let belum = 0;
            let stale = 0;
            let reviews = 0;

            for (const juz of this.juzGrid) {
                let jHafal = 0;
                let jProses = 0;
                let jStale = 0;
                for (const p of juz.pages) {
                    if (p.status === 'hafal') {
                        hafal++;
                        jHafal++;
                    } else if (p.status === 'proses') {
                        proses++;
                        jProses++;
                    } else {
                        belum++;
                    }
                    if (p.needs_murajaah) {
                        stale++;
                        jStale++;
                    }
                    reviews += Number(p.review_count || 0);
                }
                juz.hafal_count = jHafal;
                juz.proses_count = jProses;
                juz.stale_count = jStale;
                juz.completion_percent = Math.round((jHafal / juz.total_pages) * 100);
            }

            this.summary.hafal_count = hafal;
            this.summary.proses_count = proses;
            this.summary.belum_count = belum;
            this.summary.needs_murajaah_count = stale;
            this.summary.total_reviews = reviews;
            this.summary.hafal_percent = ((hafal / 604) * 100).toFixed(1);
        },

        showToast(msg) {
            this.toastMessage = msg;
            setTimeout(() => {
                if (this.toastMessage === msg) {
                    this.toastMessage = '';
                }
            }, 3200);
        },
    };
}
</script>
@endsection

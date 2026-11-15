<?php

use App\Http\Controllers\MushafReaderController;
use App\Http\Controllers\PageNoteController;
use App\Http\Controllers\ProgressController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — HafalanNuha (Page-Centric Madinah Mushaf Tahfidz App)
|--------------------------------------------------------------------------
*/

Route::get('/', [MushafReaderController::class, 'home'])->name('home');

Route::get('/mushaf/{pageNumber?}', [MushafReaderController::class, 'show'])
    ->whereNumber('pageNumber')
    ->name('mushaf.show');

Route::get('/dashboard', [ProgressController::class, 'index'])
    ->name('dashboard.index');

/*
|--------------------------------------------------------------------------
| AJAX / Fetch Endpoints (Session + CSRF Protected or Stateless API)
|--------------------------------------------------------------------------
*/
Route::prefix('api/pages/{pageNumber}')
    ->whereNumber('pageNumber')
    ->group(function () {
        Route::get('/', [MushafReaderController::class, 'pageJson'])->name('pages.json');
        Route::patch('/progress', [ProgressController::class, 'updateStatus'])->name('pages.progress.update');
        Route::post('/murajaah', [ProgressController::class, 'logMurajaah'])->name('pages.murajaah.log');
        Route::put('/notes', [PageNoteController::class, 'upsert'])->name('pages.notes.upsert');
    });

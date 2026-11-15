<?php

use App\Http\Controllers\MushafReaderController;
use App\Http\Controllers\PageNoteController;
use App\Http\Controllers\ProgressController;
use Illuminate\Support\Facades\Route;

Route::prefix('pages/{pageNumber}')
    ->whereNumber('pageNumber')
    ->group(function () {
        Route::get('/', [MushafReaderController::class, 'pageJson']);
        Route::patch('/progress', [ProgressController::class, 'updateStatus']);
        Route::post('/murajaah', [ProgressController::class, 'logMurajaah']);
        Route::put('/notes', [PageNoteController::class, 'upsert']);
    });

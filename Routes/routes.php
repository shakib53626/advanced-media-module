<?php

use App\Modules\AdvancedMedia\Http\Controllers\AdvancedMediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('advanced-media')->as('advanced-media.')->group(function () {
    Route::get('/',         [AdvancedMediaController::class, 'index'])->name('index');
    Route::post('/folder',  [AdvancedMediaController::class, 'createFolder'])->name('folder');
    Route::post('/upload',  [AdvancedMediaController::class, 'upload'])->name('upload');
    Route::patch('/rename', [AdvancedMediaController::class, 'rename'])->name('rename');
    Route::delete('/',      [AdvancedMediaController::class, 'destroy'])->name('destroy');
    Route::patch('/move',   [AdvancedMediaController::class, 'move'])->name('move');
});

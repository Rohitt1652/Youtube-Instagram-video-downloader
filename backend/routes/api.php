<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MediaGrab API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('media')->group(function () {
    Route::post('/info', [MediaController::class, 'getInfo'])
        ->middleware('throttle:media-info');

    Route::post('/download', [MediaController::class, 'createDownload'])
        ->middleware('throttle:media-download');

    Route::get('/jobs/{id}', [MediaController::class, 'getJobStatus']);

    Route::get('/file/{token}', [MediaController::class, 'downloadFile']);

    Route::get('/health', [MediaController::class, 'health']);
});

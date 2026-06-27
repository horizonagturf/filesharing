<?php

use App\Http\Controllers\BundleController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [WebController::class, 'login'])->name('login');
Route::post('/login', [WebController::class, 'doLogin'])->name('login.post');
Route::get('/logout', [WebController::class, 'logout'])->name('logout');

Route::middleware(['can.upload'])->group(function () {
    Route::get('/', [WebController::class, 'homepage'])->name('homepage');
    Route::post('/new', [WebController::class, 'newBundle'])->name('bundle.new');

    Route::prefix('/upload/{bundle}')->name('upload.')->group(function () {
        Route::get('/', [UploadController::class, 'createBundle'])->name('create.show');

        Route::middleware(['access.owner'])->group(function () {
            Route::post('/', [UploadController::class, 'storeBundle'])->name('create.store');
            Route::post('/file', [UploadController::class, 'uploadFile'])->name('file.store');
            Route::delete('/file', [UploadController::class, 'deleteFile'])->name('file.delete');
            Route::post('/complete', [UploadController::class, 'completeBundle'])->name('complete');
            Route::delete('/delete', [UploadController::class, 'deleteBundle'])->name('bundle.delete');
        });
    });
});

Route::middleware(['access.guest'])->prefix('/bundle/{bundle}')->name('bundle.')->group(function () {
    Route::get('/preview', [BundleController::class, 'previewBundle'])->name('preview');
    Route::get('/download', [BundleController::class, 'downloadZip'])->name('zip.download');
});

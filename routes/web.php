<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/help/{topic}', [HelpController::class, 'show'])
    ->name('help.show')
    ->where('topic', '[a-z0-9\-]+');

Route::get('/login', [WebController::class, 'login'])->name('login');
Route::post('/login', [WebController::class, 'doLogin'])->name('login.post');
Route::get('/logout', [WebController::class, 'logout'])->name('logout');

Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
    ->middleware('throttle:oauth')
    ->name('auth.microsoft.callback');

Route::middleware(['auth'])->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
});

Route::middleware(['can.upload'])->group(function () {
    Route::get('/', [WebController::class, 'homepage'])->name('homepage');
    Route::post('/new', [WebController::class, 'newBundle'])->name('bundle.new');

    Route::prefix('/upload/{bundle}')->name('upload.')->group(function () {
        Route::get('/', [UploadController::class, 'createBundle'])->name('create.show');
        Route::get('/file/{file}/thumbnail', [UploadController::class, 'thumbnail'])->name('file.thumbnail');

        Route::middleware(['access.owner'])->group(function () {
            Route::post('/', [UploadController::class, 'storeBundle'])->name('create.store');
            Route::post('/file', [UploadController::class, 'uploadFile'])->name('file.store');
            Route::delete('/file', [UploadController::class, 'deleteFile'])->name('file.delete');
            Route::post('/complete', [UploadController::class, 'completeBundle'])->name('complete');
            Route::delete('/delete', [UploadController::class, 'deleteBundle'])->name('bundle.delete');
            Route::post('/recipients/{recipient}/resend', [UploadController::class, 'resendInvitation'])->name('recipients.resend');
            Route::post('/recipients/{recipient}/revoke', [UploadController::class, 'revokeInvitation'])->name('recipients.revoke');
        });
    });
});

Route::middleware(['signed'])->prefix('/invitation/{bundle}/{recipient}')->name('invitation.')->group(function () {
    Route::get('/', [InvitationController::class, 'show'])->name('show');
    Route::post('/otp', [InvitationController::class, 'requestOtp'])->middleware('throttle:otp')->name('otp.request');
    Route::post('/verify', [InvitationController::class, 'verifyOtp'])->middleware('throttle:otp')->name('otp.verify');
});

Route::middleware(['auth', 'role:reviewer'])->prefix('/approval')->name('approval.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::get('/{approvalRequest}', [ApprovalController::class, 'show'])->name('show');
    Route::get('/{approvalRequest}/file/{file}/thumbnail', [ApprovalController::class, 'thumbnail'])->name('file.thumbnail');
    Route::get('/{approvalRequest}/file/{file}/download', [ApprovalController::class, 'downloadFile'])->name('file.download');
    Route::post('/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('/{approvalRequest}/deny', [ApprovalController::class, 'deny'])->name('deny');
});

Route::middleware(['access.guest', 'throttle:download'])->prefix('/bundle/{bundle}')->name('bundle.')->group(function () {
    Route::get('/preview', [BundleController::class, 'previewBundle'])->name('preview');
    Route::get('/download', [BundleController::class, 'downloadZip'])->name('zip.download');
    Route::post('/unlock', [BundleController::class, 'unlock'])->name('unlock');
    Route::get('/file/{file}/thumbnail', [BundleController::class, 'thumbnail'])->name('file.thumbnail');
    Route::get('/file/{file}/download', [BundleController::class, 'downloadFile'])->name('file.download');
});

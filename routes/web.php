<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Board\BoardController;
use App\Http\Controllers\User\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// +-+-+-+-+-+-+-+- 未ログインのみ +-+-+-+-+-+-+-+-
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// +-+-+-+-+-+-+-+- ログイン必須 +-+-+-+-+-+-+-+-
Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [BoardController::class, 'index'])->name('boards.index');

    // +-+-+-+-+-+-+-+- システム管理者のみ +-+-+-+-+-+-+-+-
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });
});

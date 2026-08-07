<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectFavoriteController;
use App\Http\Controllers\Project\ProjectGroupController;
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

    // ダッシュボード（自分が参加しているプロジェクトの一覧）
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // プロジェクトをまとめるグループ（名前だけの入れ物。所属は任意）
    Route::resource('project-groups', ProjectGroupController::class)->except('show');

    // プロジェクト（一覧はダッシュボードが兼ねる）
    // 参加していないプロジェクトは ProjectPolicy で 404 になる
    Route::post('projects/{project}/favorite', ProjectFavoriteController::class)->name('projects.favorite');
    Route::resource('projects', ProjectController::class)->except('index');

    // +-+-+-+-+-+-+-+- システム管理者のみ +-+-+-+-+-+-+-+-
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });
});

<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectFavoriteController;
use App\Http\Controllers\Project\ProjectGroupAssignmentController;
use App\Http\Controllers\Project\ProjectGroupController;
use App\Http\Controllers\Project\TaskChecklistController;
use App\Http\Controllers\Project\TaskCommentAttachmentController;
use App\Http\Controllers\Project\TaskCommentController;
use App\Http\Controllers\Project\TaskController;
use App\Http\Controllers\Project\TaskDetailController;
use App\Http\Controllers\Project\TaskLabelController;
use App\Http\Controllers\Project\TaskListController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\UserImportController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// +-+-+-+-+-+-+-+- 未ログインのみ +-+-+-+-+-+-+-+-
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// +-+-+-+-+-+-+-+- ログイン必須 +-+-+-+-+-+-+-+-
Route::middleware(['auth', EnsureUserIsActive::class, EnsurePasswordIsChanged::class])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // ダッシュボード（自分が参加しているプロジェクトの一覧）
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // 通知（自分へのメンションと、フォローしたタスクの動き）
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/activities/{notification}', [NotificationController::class, 'openActivity'])->name('notifications.activities.open');
    Route::get('notifications/{comment}', [NotificationController::class, 'open'])->name('notifications.open');

    // 自分のプロフィール
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('profile/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');
    Route::put('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    // プロジェクトをまとめるグループ（名前と並び順だけの入れ物。所属は任意）
    Route::post('project-groups/reorder', [ProjectGroupController::class, 'reorder'])->name('project-groups.reorder');
    Route::resource('project-groups', ProjectGroupController::class)->except('show');

    // プロジェクト（一覧はダッシュボードが兼ねる）
    // 参加していないプロジェクトは ProjectPolicy で 404 になる
    // まずアーカイブし、完全な削除はアーカイブ済みのものだけできる
    // /projects/{project} に飲まれないよう resource より先に置く
    Route::get('projects/archived', [ProjectController::class, 'archived'])->name('projects.archived');
    Route::get('projects/move-targets', [ProjectController::class, 'moveTargets'])->name('projects.move-targets');
    Route::get('projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('projects/{project}/unarchive', [ProjectController::class, 'unarchive'])->name('projects.unarchive');
    Route::post('projects/{project}/favorite', ProjectFavoriteController::class)->name('projects.favorite');
    // 自分のダッシュボードでの分類なので、参加者なら誰でも変えられる
    Route::put('projects/{project}/group', ProjectGroupAssignmentController::class)->name('projects.group.update');

    // カンバン（1プロジェクト＝1ボード）。参加者なら誰でも操作できる
    Route::prefix('projects/{project}')->group(function () {
        Route::post('lists', [TaskListController::class, 'store'])->name('lists.store');
        Route::post('lists/reorder', [TaskListController::class, 'reorder'])->name('lists.reorder');
        Route::post('tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
        Route::post('tasks/archive', [TaskController::class, 'archiveMany'])->name('tasks.archive-many');
        Route::post('tasks/unarchive', [TaskController::class, 'unarchiveMany'])->name('tasks.unarchive-many');
    });
    Route::put('lists/{list}', [TaskListController::class, 'update'])->name('lists.update');
    Route::post('lists/{list}/duplicate', [TaskListController::class, 'duplicate'])->name('lists.duplicate');
    Route::post('lists/{list}/archive', [TaskListController::class, 'archive'])->name('lists.archive');
    Route::post('lists/{list}/unarchive', [TaskListController::class, 'unarchive'])->name('lists.unarchive');
    Route::delete('lists/{list}', [TaskListController::class, 'destroy'])->name('lists.destroy');
    Route::post('lists/{list}/tasks', [TaskController::class, 'store'])->name('tasks.store');

    // タスクの詳細。どれも詳細画面の中身を返す
    Route::get('tasks/{task}', [TaskDetailController::class, 'show'])->name('tasks.show');
    Route::put('tasks/{task}', [TaskDetailController::class, 'update'])->name('tasks.update');
    Route::put('tasks/{task}/status', [TaskDetailController::class, 'status'])->name('tasks.status');
    Route::put('tasks/{task}/period', [TaskDetailController::class, 'period'])->name('tasks.period');
    Route::post('tasks/{task}/assignee', [TaskDetailController::class, 'assignee'])->name('tasks.assignee');
    Route::post('tasks/{task}/follow', [TaskDetailController::class, 'follow'])->name('tasks.follow');
    Route::put('tasks/{task}/labels', [TaskLabelController::class, 'sync'])->name('tasks.labels.sync');
    Route::post('tasks/{task}/labels', [TaskLabelController::class, 'store'])->name('tasks.labels.store');
    Route::post('tasks/{task}/labels/reorder', [TaskLabelController::class, 'reorder'])->name('tasks.labels.reorder');
    Route::put('tasks/{task}/labels/{label}', [TaskLabelController::class, 'update'])->name('tasks.labels.update');
    Route::delete('tasks/{task}/labels/{label}', [TaskLabelController::class, 'destroy'])->name('tasks.labels.destroy');
    Route::post('tasks/{task}/checklists', [TaskChecklistController::class, 'store'])->name('tasks.checklists.store');
    Route::post('tasks/{task}/checklists/reorder', [TaskChecklistController::class, 'reorder'])->name('tasks.checklists.reorder');
    Route::put('checklists/{checklist}', [TaskChecklistController::class, 'update'])->name('checklists.update');
    Route::delete('checklists/{checklist}', [TaskChecklistController::class, 'destroy'])->name('checklists.destroy');
    Route::post('checklists/{checklist}/items', [TaskChecklistController::class, 'storeItem'])->name('checklists.items.store');
    Route::post('checklists/{checklist}/items/reorder', [TaskChecklistController::class, 'reorderItems'])->name('checklists.items.reorder');
    Route::post('checklist-items/{item}/toggle', [TaskChecklistController::class, 'toggleItem'])->name('checklist-items.toggle');
    Route::put('checklist-items/{item}/period', [TaskChecklistController::class, 'periodItem'])->name('checklist-items.period');
    Route::post('checklist-items/{item}/assignee', [TaskChecklistController::class, 'assignItem'])->name('checklist-items.assignee');
    Route::delete('checklist-items/{item}', [TaskChecklistController::class, 'destroyItem'])->name('checklist-items.destroy');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('comments/{comment}/reactions', [TaskCommentController::class, 'react'])->name('comments.react');
    Route::get('attachments/{attachment}', [TaskCommentAttachmentController::class, 'show'])->name('attachments.show');

    Route::post('tasks/{task}/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::post('tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
    Route::post('tasks/{task}/unarchive', [TaskController::class, 'unarchive'])->name('tasks.unarchive');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::resource('projects', ProjectController::class)->except('index');

    // +-+-+-+-+-+-+-+- システム管理者のみ +-+-+-+-+-+-+-+-
    Route::middleware('can:manage-users')->group(function () {
        Route::get('users/deleted', [UserController::class, 'deleted'])->name('users.deleted');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.password.reset');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
        Route::get('users/import', [UserImportController::class, 'create'])->name('users.import');
        Route::post('users/import', [UserImportController::class, 'store'])->name('users.import.store');
        Route::get('users/import/template', [UserImportController::class, 'template'])->name('users.import.template');
        Route::resource('users', UserController::class)->except('show');
    });
});

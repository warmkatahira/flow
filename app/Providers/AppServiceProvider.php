<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // システム管理者はすべての操作ができる
        Gate::before(fn (User $user) => $user->isSystemAdmin() ? true : null);

        // ユーザー管理はシステム管理者のみ（管理者と一般の区分けは今後検討する）
        Gate::define('manage-users', fn (User $user) => $user->isSystemAdmin());
    }
}

<?php

namespace App\Providers;

use App\Models\Project;
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
        // システム管理者はすべての操作ができる。
        // ただしプロジェクトだけは例外で、秘匿性を優先して ProjectPolicy に判断を任せる
        // （参加していないプロジェクトはふつうの画面ではシステム管理者にも見せない。
        //  見るだけなら「プロジェクト管理」の別の入口から見られる）。
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $subject = $arguments[0] ?? null;

            if ($subject instanceof Project || $subject === Project::class) {
                return null;
            }

            return $user->isSystemAdmin() ? true : null;
        });

        // ユーザー管理はシステム管理者のみ（管理者と一般の区分けは今後検討する）
        Gate::define('manage-users', fn (User $user) => $user->isSystemAdmin());

        // プロジェクト管理で全員のプロジェクトを見る（見るだけ。操作は ProjectPolicy が今まで通り拒否する）
        Gate::define('browse-projects', fn (User $user) => $user->isSystemAdmin());
    }
}

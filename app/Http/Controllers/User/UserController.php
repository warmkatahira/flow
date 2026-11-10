<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * ユーザー管理（システム管理者のみ）
 */
class UserController extends Controller
{
    /**
     * ユーザー一覧を表示する
     */
    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderBy('id')->paginate(20),
        ]);
    }

    /**
     * ユーザー登録画面を表示する
     */
    public function create(): View
    {
        return view('users.create', [
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * ユーザーを登録する
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        [$user, $password] = DB::transaction(function () use ($request) {
            $user = new User($request->validated());
            $password = $user->issuePassword();

            // 本人専用の My Project を最初から用意する
            Project::createPersonalFor($user);

            return [$user, $password];
        });

        return redirect()
            ->route('users.index')
            ->with('status', 'ユーザーを登録しました。')
            ->with('issuedPasswords', [[...$user->only('name', 'email'), 'password' => $password]]);
    }

    /**
     * ユーザー編集画面を表示する
     */
    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * ユーザーを更新する
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $attributes = $request->validated();

        // 自分自身の権限を下げてユーザー管理に入れなくなるのを防ぐ
        if ($request->user()->is($user) && $attributes['role'] !== $user->role->value) {
            return back()
                ->withInput()
                ->withErrors(['role' => '自分自身の権限は変更できません。']);
        }

        // 自分自身を無効にしてログインできなくなるのを防ぐ
        if ($request->user()->is($user) && ! $request->boolean('is_active')) {
            return back()
                ->withInput()
                ->withErrors(['is_active' => '自分自身を無効にはできません。']);
        }

        $user->update($attributes);

        return redirect()
            ->route('users.index')
            ->with('status', 'ユーザーを更新しました。');
    }

    /**
     * ユーザーを削除する
     */
    public function destroy(User $user): RedirectResponse
    {
        if (request()->user()->is($user)) {
            return back()->with('error', '自分自身は削除できません。');
        }

        if ($user->isSystemAdmin()) {
            return back()->with('error', 'システム管理者は削除できません。');
        }

        // ソフトデリート。参加しているプロジェクトやアイコン画像は復元に備えて残す
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('toast', ['message' => '削除しました', 'url' => route('users.restore', $user)]);
    }

    /**
     * パスワードをリセットする（新しいパスワードを発行し、次回ログイン時に変更が必須になる）
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        // 自分のパスワードはプロフィールから変える
        if ($request->user()->is($user)) {
            return back()->with('error', '自分自身のパスワードはリセットできません。');
        }

        $password = $user->issuePassword();

        return redirect()
            ->route('users.index')
            ->with('status', "{$user->name} のパスワードをリセットしました。")
            ->with('issuedPasswords', [[...$user->only('name', 'email'), 'password' => $password]]);
    }

    /**
     * 削除済みユーザーの一覧を表示する
     */
    public function deleted(): View
    {
        return view('users.deleted', [
            'users' => User::onlyTrashed()->latest('deleted_at')->paginate(20),
        ]);
    }

    /**
     * 削除済みユーザーを元に戻す
     */
    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        return redirect()
            ->route('users.deleted')
            ->with('status', "{$user->name} を元に戻しました。");
    }
}

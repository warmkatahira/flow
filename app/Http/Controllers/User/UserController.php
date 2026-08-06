<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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
        User::create($request->validated());

        return redirect()
            ->route('users.index')
            ->with('status', 'ユーザーを登録しました。');
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

        // パスワードは入力されたときだけ変更する
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

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

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'ユーザーを削除しました。');
    }
}

<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * 自分のパスワードの変更
 *
 * 発行されたパスワードで初めてログインしたときは、ここでの変更が必須になる。
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.password', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)],
        ], [], [
            'current_password' => '現在のパスワード',
            'password' => '新しいパスワード',
        ]);

        $user = $request->user();
        $wasRequired = $user->must_change_password;

        $user->password = $attributes['password'];
        $user->must_change_password = false;
        $user->save();

        return redirect()
            ->route($wasRequired ? 'dashboard' : 'profile.edit')
            ->with('status', 'パスワードを変更しました。');
    }
}

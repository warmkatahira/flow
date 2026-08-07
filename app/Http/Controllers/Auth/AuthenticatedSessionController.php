<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * ログイン画面を表示する
     */
    public function create(): View
    {
        // テスト環境ではすぐログインできるようにテスト用アカウントを初期値として埋める
        $testAccount = app()->isProduction() ? null : config('flow.test_account');

        return view('auth.login', [
            'defaultEmail' => $testAccount['email'] ?? null,
            'defaultPassword' => $testAccount['password'] ?? null,
        ]);
    }

    /**
     * ログインする
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // セッション固定攻撃対策
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * ログアウトする
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * 自分のプロフィール
 *
 * 対象は常にログイン中の本人なので、URL に ID を持たせない。
 */
class ProfileController extends Controller
{
    /**
     * プロフィール画面を表示する
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * アイコン画像を差し替える
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate(
            ['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            [],
            ['avatar' => 'アイコン画像'],
        );

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        $user->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('status', 'アイコン画像を更新しました。');
    }

    /**
     * アイコン画像を削除して頭文字の表示に戻す
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);

            $user->avatar_path = null;
            $user->save();
        }

        return back()->with('status', 'アイコン画像を削除しました。');
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * 連続失敗を許容する回数
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * ロック時間（秒）
     */
    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * 認証を試みる
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // 無効なユーザー（退職者など）はログインさせない
        if (! Auth::user()->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'このアカウントは無効です。管理者にお問い合わせください。',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * 短時間に失敗を繰り返していないか確認する
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    /**
     * メールアドレス＋IPごとにレート制限する
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}

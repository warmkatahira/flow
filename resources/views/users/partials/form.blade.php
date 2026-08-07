@csrf

{{-- 名前 --}}
<div>
    <x-input-label for="name" value="名前" :required="true" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $user?->name)"
                  required autofocus />
    <x-input-error :messages="$errors->get('name')" />
</div>

{{-- メールアドレス --}}
<div>
    <x-input-label for="email" value="メールアドレス（ログインID）" :required="true" />
    <x-text-input id="email" name="email" type="email" :value="old('email', $user?->email)"
                  required placeholder="you@example.com" />
    <x-input-error :messages="$errors->get('email')" />
</div>

{{-- 権限 --}}
<div>
    <x-input-label for="role" value="権限" :required="true" />
    <x-select-input id="role" name="role" :options="$roles"
                    :selected="old('role', $user?->role->value ?? \App\Enums\UserRole::General->value)" required />
    <x-input-error :messages="$errors->get('role')" />
    <p class="mt-1 text-xs text-slate-500">
        ユーザー管理ができるのはシステム管理者のみです。
    </p>
</div>

{{-- ステータス --}}
<div>
    <x-input-label for="is_active" value="ステータス" :required="true" />
    <x-select-input id="is_active" name="is_active" :options="[1 => '有効', 0 => '無効']"
                    :selected="old('is_active', (int) ($user?->is_active ?? true))" required />
    <x-input-error :messages="$errors->get('is_active')" />
    <p class="mt-1 text-xs text-slate-500">
        無効にするとログインできなくなります（退職者など）。
    </p>
</div>

{{-- パスワード --}}
<div>
    <x-input-label for="password" value="パスワード" :required="$user === null" />
    <x-text-input id="password" name="password" type="password"
                  :required="$user === null" />
    <x-input-error :messages="$errors->get('password')" />
    @if ($user)
        <p class="mt-1 text-xs text-slate-500">変更する場合のみ入力してください。</p>
    @else
        <p class="mt-1 text-xs text-slate-500">8文字以上で入力してください。</p>
    @endif
</div>

{{-- パスワード（確認） --}}
<div>
    <x-input-label for="password_confirmation" value="パスワード（確認）" :required="$user === null" />
    <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                  :required="$user === null" />
    <x-input-error :messages="$errors->get('password_confirmation')" />
</div>

<div class="flex items-center gap-3 pt-2">
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
    <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:text-slate-900">キャンセル</a>
</div>

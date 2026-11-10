<x-layouts.guest>
    <h1 class="text-lg font-bold text-slate-800 mb-6">ログイン</h1>

    <form autocomplete="off" method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        {{-- メールアドレス --}}
        <div>
            <x-input-label for="email" value="メールアドレス" :required="true" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $defaultEmail)"
                          required autofocus placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        {{-- パスワード --}}
        <div>
            <x-input-label for="password" value="パスワード" :required="true" />
            <x-text-input id="password" name="password" type="password" :value="$defaultPassword"
                          required />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <x-primary-button class="w-full">ログイン</x-primary-button>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        アカウントが必要な場合は管理者にお問い合わせください。
    </p>
</x-layouts.guest>

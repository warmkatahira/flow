<x-layouts.app title="パスワード変更">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">パスワード変更</h1>
    </x-slot:header>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @if ($user->must_change_password)
            <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                発行されたパスワードでログインしています。使い始める前に、自分のパスワードに変更してください。
            </p>
        @endif

        <div class="rounded-xl bg-white px-6 py-8 shadow-sm">
            <form autocomplete="off" method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="current_password" value="現在のパスワード" :required="true" />
                    <x-text-input id="current_password" name="current_password" type="password" required autofocus />
                    <x-input-error :messages="$errors->get('current_password')" />
                </div>

                <div>
                    <x-input-label for="password" value="新しいパスワード" :required="true" />
                    <x-text-input id="password" name="password" type="password" required />
                    <x-input-error :messages="$errors->get('password')" />
                    <p class="mt-1 text-xs text-slate-500">8文字以上で入力してください。</p>
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="新しいパスワード（確認）" :required="true" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" required />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>変更する</x-primary-button>
                    @unless ($user->must_change_password)
                        <a href="{{ route('profile.edit') }}" class="text-sm text-slate-600 hover:text-slate-900">キャンセル</a>
                    @endunless
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

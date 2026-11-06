<x-layouts.app title="CSV でユーザー登録">
    <x-slot:header>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-lg font-bold text-slate-800">CSV でユーザー登録</h1>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:text-slate-900">一覧へ戻る</a>
        </div>
    </x-slot:header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @if (session('importProblems'))
            <div class="rounded-xl bg-rose-50 px-5 py-4 ring-1 ring-rose-200">
                <h2 class="text-sm font-bold text-rose-800">
                    問題のある行があるため、1件も登録していません。CSV を直してもう一度アップロードしてください。
                </h2>
                <ul class="mt-2 space-y-1 text-sm text-rose-700">
                    @foreach (session('importProblems') as $number => $messages)
                        @foreach ($messages as $message)
                            <li>{{ $number }}行目: {{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl bg-white px-6 py-6 shadow-sm">
            <form autocomplete="off" method="POST" action="{{ route('users.import.store') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="csv" value="CSV ファイル" :required="true" />
                    <input autocomplete="off" id="csv" name="csv" type="file" accept=".csv,text/csv" required
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100">
                    <p class="mt-1 text-xs text-slate-500">
                        1MB、{{ $maxRows }}件まで。UTF-8 / Shift_JIS（Excel の CSV）に対応しています。
                    </p>
                    <x-input-error :messages="$errors->get('csv')" />
                </div>

                <x-primary-button>登録する</x-primary-button>
            </form>
        </div>

        <div class="rounded-xl bg-white px-6 py-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-700">CSV の形式</h2>
                <a href="{{ route('users.import.template') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">雛形をダウンロード</a>
            </div>

            <p class="mt-2 text-xs text-slate-500">1行目は見出しにしてください。列の順番は問いません。</p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">見出し</th>
                            <th class="px-3 py-2 text-left font-medium">内容</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr><td class="px-3 py-2 whitespace-nowrap">名前</td><td class="px-3 py-2">必須</td></tr>
                        <tr><td class="px-3 py-2 whitespace-nowrap">メールアドレス</td><td class="px-3 py-2">必須。ログイン ID になります</td></tr>
                        <tr><td class="px-3 py-2 whitespace-nowrap">権限</td><td class="px-3 py-2">{{ implode(' / ', $roles) }}。省略すると一般</td></tr>
                        <tr><td class="px-3 py-2 whitespace-nowrap">ステータス</td><td class="px-3 py-2">有効 / 無効。省略すると有効</td></tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-slate-500">
                パスワードは自動で発行し、登録後に一度だけ表示します（CSV でダウンロードできます）。本人は初回ログイン時に変更します。
            </p>
        </div>
    </div>
</x-layouts.app>

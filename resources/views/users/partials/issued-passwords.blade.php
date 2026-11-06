{{-- 発行したパスワードは保存していないので、平文を見られるのはこの画面の1回だけ --}}
@if (session('issuedPasswords'))
    <div x-data="issuedPasswords(@js(session('issuedPasswords')))"
         class="mb-4 rounded-xl bg-amber-50 px-5 py-4 ring-1 ring-amber-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-amber-800">発行したパスワード</h2>
                <p class="mt-1 text-xs text-amber-700">
                    この画面を離れると二度と表示できません。本人に伝えてください。初回ログイン時に変更が必要です。
                </p>
            </div>
            <button type="button" @click="download()"
                    class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                CSV でダウンロード
            </button>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-amber-700">
                    <tr>
                        <th class="py-1 pr-4 font-medium">名前</th>
                        <th class="py-1 pr-4 font-medium">メールアドレス</th>
                        <th class="py-1 font-medium">パスワード</th>
                    </tr>
                </thead>
                <tbody class="text-slate-800">
                    @foreach (session('issuedPasswords') as $issued)
                        <tr>
                            <td class="py-1 pr-4">{{ $issued['name'] }}</td>
                            <td class="py-1 pr-4 break-all">{{ $issued['email'] }}</td>
                            <td class="py-1 font-mono whitespace-nowrap">{{ $issued['password'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

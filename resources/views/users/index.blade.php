<x-layouts.app title="ユーザー管理">
    <x-slot:header>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-bold text-slate-800">ユーザー管理</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('users.deleted') }}" class="px-2 text-sm text-slate-600 hover:text-slate-900">削除済み</a>
                <a href="{{ route('users.import') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    CSV で登録
                </a>
                <a href="{{ route('users.create') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                    新規登録
                </a>
            </div>
        </div>
    </x-slot:header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-session-status :status="session('status')" />

        @include('users.partials.issued-passwords')

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">名前</th>
                        <th class="px-4 py-3 text-left font-medium">メールアドレス</th>
                        <th class="px-4 py-3 text-left font-medium">権限</th>
                        <th class="px-4 py-3 text-left font-medium">ステータス</th>
                        <th class="px-4 py-3 text-left font-medium">最終ログイン</th>
                        <th class="px-4 py-3 text-right font-medium">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-800">
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$user" />
                                    <span class="min-w-0">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 break-all">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $user->role->badgeClass() }}">
                                    {{ $user->role->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $user->is_active ? '有効' : '無効' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                {{ $user->last_login_at?->format('Y/m/d H:i') ?? '未ログイン' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center justify-center rounded-lg border bg-white px-2.5 py-1 text-xs font-semibold transition border-slate-300 text-slate-700 hover:bg-slate-50">編集</a>

                                    @unless (auth()->user()->is($user))
                                        <form autocomplete="off" method="POST" action="{{ route('users.password.reset', $user) }}"
                                              onsubmit="return confirm(@js($user->name.' のパスワードをリセットします。今のパスワードは使えなくなります。よろしいですか？'));">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border bg-white px-2.5 py-1 text-xs font-semibold transition border-amber-300 text-amber-700 hover:bg-amber-50">パスワードリセット</button>
                                        </form>
                                    @endunless

                                    @unless (auth()->user()->is($user) || $user->isSystemAdmin())
                                        <form autocomplete="off" method="POST" action="{{ route('users.destroy', $user) }}"
                                              onsubmit="return confirm(@js($user->name.' を削除します。ログインできなくなりますが、削除済みの一覧から元に戻せます。よろしいですか？'));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border bg-white px-2.5 py-1 text-xs font-semibold transition border-rose-300 text-rose-600 hover:bg-rose-50">削除</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">ユーザーがいません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
</x-layouts.app>

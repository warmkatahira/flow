<x-layouts.app title="ユーザー管理">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-bold text-slate-800">ユーザー管理</h1>
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                新規登録
            </a>
        </div>
    </x-slot:header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-session-status :status="session('status')" />

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">名前</th>
                        <th class="px-4 py-3 text-left font-medium">メールアドレス</th>
                        <th class="px-4 py-3 text-left font-medium">権限</th>
                        <th class="px-4 py-3 text-left font-medium">ステータス</th>
                        <th class="px-4 py-3 text-right font-medium">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-800">{{ $user->name }}</td>
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
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('users.edit', $user) }}" class="text-sky-600 hover:text-sky-800">編集</a>

                                    @unless (auth()->user()->is($user))
                                        <form autocomplete="off" method="POST" action="{{ route('users.destroy', $user) }}"
                                              onsubmit="return confirm('{{ $user->name }} を削除します。よろしいですか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-800">削除</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">ユーザーがいません。</td>
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

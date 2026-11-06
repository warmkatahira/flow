<x-layouts.app title="削除済みユーザー">
    <x-slot:header>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-lg font-bold text-slate-800">削除済みユーザー</h1>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:text-slate-900">一覧へ戻る</a>
        </div>
    </x-slot:header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-session-status :status="session('status')" />

        @if ($users->isEmpty())
            <p class="rounded-xl bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                削除済みのユーザーはいません。
            </p>
        @else
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm">
                @foreach ($users as $user)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <x-user-avatar :user="$user" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-800">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $user->email }}・{{ $user->deleted_at->format('Y/m/d') }} に削除
                            </p>
                        </div>
                        <form autocomplete="off" method="POST" action="{{ route('users.restore', $user) }}">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-sky-700 hover:text-sky-900">元に戻す</button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>

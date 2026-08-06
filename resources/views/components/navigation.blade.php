<nav x-data="{ open: false }" class="bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">
            <div class="flex items-center">
                <a href="{{ route('boards.index') }}">
                    <x-application-logo class="h-7" />
                </a>
            </div>

            <div class="flex items-center relative">
                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 px-3 py-2 rounded-lg hover:bg-slate-100">
                    <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-sky-600 text-white text-xs font-bold">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </span>
                    <span>{{ auth()->user()->name }}</span>
                    <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="open" x-transition @click.outside="open = false" x-cloak
                     class="absolute right-0 top-12 w-52 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs text-slate-500 break-all">{{ auth()->user()->email }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ auth()->user()->role->label() }}</p>
                    </div>

                    {{-- ユーザー管理はシステム管理者のみ --}}
                    @can('manage-users')
                        <a href="{{ route('users.index') }}"
                           class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">
                            ユーザー管理
                        </a>
                    @endcan

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">
                            ログアウト
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>

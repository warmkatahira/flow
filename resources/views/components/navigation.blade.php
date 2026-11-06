@php
    $hasSidebar = ! auth()->user()->must_change_password;

    $tools = [
        ['label' => 'ダッシュボード', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'アーカイブ', 'route' => 'projects.archived', 'active' => 'projects.archived'],
        ['label' => 'グループ', 'route' => 'project-groups.index', 'active' => 'project-groups.*'],
    ];

    // ユーザー管理はシステム管理者のみ
    if (auth()->user()->can('manage-users')) {
        $tools[] = ['label' => 'ユーザー管理', 'route' => 'users.index', 'active' => 'users.*'];
    }
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-slate-200">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">
            <div class="flex min-w-0 flex-1 items-center gap-4">
                @if ($hasSidebar)
                    {{-- 矢印は押すと動く向き。開いていれば左、畳んでいれば右 --}}
                    <button type="button" @click="$store.sidebar.toggle()"
                            aria-label="プロジェクトリストの開閉" title="プロジェクトリストの開閉"
                            class="flex shrink-0 items-center rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
                        <svg class="h-4 w-4 rotate-180 transition-transform duration-200 lg:rotate-0 lg:sidebar-closed:rotate-180"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                  d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" />
                        </svg>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                  d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 5.25a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10zm.75 4.5a.75.75 0 000 1.5h14.5a.75.75 0 000-1.5H2.75z" />
                        </svg>
                    </button>
                @endif

                {{-- リストを出している間はロゴがその上にあるので、ここには出さない --}}
                <a href="{{ route('dashboard') }}"
                   class="shrink-0 {{ $hasSidebar ? 'lg:hidden lg:sidebar-closed:block' : '' }}">
                    <x-application-logo class="h-7" />
                </a>

                {{-- ツールバー。狭い画面では横にスクロールする --}}
                <div class="flex min-w-0 items-center gap-1 overflow-x-auto [scrollbar-width:none]">
                    @foreach ($tools as $tool)
                        <a href="{{ route($tool['route']) }}"
                           @if (request()->routeIs($tool['active'])) aria-current="page" @endif
                           class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs($tool['active']) ? 'bg-sky-50 text-sky-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            {{ $tool['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="flex shrink-0 items-center relative">
                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 px-3 py-2 rounded-lg hover:bg-slate-100">
                    <x-user-avatar :user="auth()->user()" size="xs" />
                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
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

                    <a href="{{ route('profile.edit') }}"
                       class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">
                        プロフィール
                    </a>

                    <form autocomplete="off" method="POST" action="{{ route('logout') }}">
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

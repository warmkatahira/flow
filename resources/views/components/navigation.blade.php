@props(['hasSidebar' => false])

@php
    $tools = [
        ['label' => 'ダッシュボード', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'タスク一覧', 'route' => 'my-tasks.index', 'active' => 'my-tasks.*'],
        ['label' => 'アーカイブ', 'route' => 'projects.archived', 'active' => 'projects.archived'],
        ['label' => 'グループ', 'route' => 'project-groups.index', 'active' => 'project-groups.*'],
    ];

    // ユーザー管理はシステム管理者のみ
    if (auth()->user()->can('manage-users')) {
        $tools[] = ['label' => 'ユーザー管理', 'route' => 'users.index', 'active' => 'users.*'];
    }

    // 「システム管理」のメニュー。項目はここに足していく（どれも見られる人がいなければメニューごと出さない）
    $adminTools = [];

    if (auth()->user()->can('browse-projects')) {
        $adminTools[] = ['label' => 'プロジェクト管理', 'route' => 'admin.projects.index', 'active' => 'admin.projects.*'];
    }

    $adminActive = collect($adminTools)->contains(fn ($tool) => request()->routeIs($tool['active']));
@endphp

{{-- 左のプロジェクトリストと同じ紺にして、画面の外枠を L 字につなげる（高さと下の線もリストのロゴの行に揃える） --}}
<nav x-data="{ open: false }" class="border-b border-slate-700 bg-slate-800">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">
            <div class="flex min-w-0 flex-1 items-center gap-4">
                @if ($hasSidebar)
                    {{-- 矢印は押すと動く向き。開いていれば左、畳んでいれば右 --}}
                    <button type="button" @click="$store.sidebar.toggle()"
                            aria-label="プロジェクトリストの開閉" title="プロジェクトリストの開閉"
                            class="flex shrink-0 items-center rounded-lg p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white">
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
                    <x-application-logo class="h-7" inverted />
                </a>

                {{-- ツールバー。狭い画面では横にスクロールする --}}
                <div class="flex min-w-0 items-center gap-1 overflow-x-auto [scrollbar-width:none]">
                    @foreach ($tools as $tool)
                        <a href="{{ route($tool['route']) }}"
                           @if (request()->routeIs($tool['active'])) aria-current="page" @endif
                           class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs($tool['active']) ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            {{ $tool['label'] }}
                        </a>
                    @endforeach
                </div>

                {{-- ツールバーは横にスクロールして中身を切り抜くので、開くメニューはその外に置く --}}
                @if ($adminTools !== [])
                    <div x-data="{ adminOpen: false }" class="relative shrink-0" @click.outside="adminOpen = false" @keydown.escape.window="adminOpen = false">
                        <button type="button" @click="adminOpen = ! adminOpen" :aria-expanded="adminOpen ? 'true' : 'false'"
                                class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $adminActive ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            システム管理
                            <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="adminOpen" x-cloak x-transition
                             class="absolute left-0 top-10 z-50 w-52 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            @foreach ($adminTools as $tool)
                                <a href="{{ route($tool['route']) }}"
                                   @if (request()->routeIs($tool['active'])) aria-current="page" @endif
                                   class="block px-4 py-2 text-sm transition {{ request()->routeIs($tool['active']) ? 'font-semibold text-sky-700' : 'text-slate-700 hover:bg-slate-100' }}">
                                    {{ $tool['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex shrink-0 items-center relative">
                {{-- パスワードの変更が済むまでは他の画面へ行けないので出さない --}}
                @unless (auth()->user()->must_change_password)
                    {{-- タスクの検索。「/」でどこからでも入力できる。結果はサーバーが組み立てる（tasks.partials.search-results） --}}
                    <div x-data="taskSearch(@js(route('tasks.search')))" class="relative mr-2"
                         @click.outside="open = false" @keydown.escape.window="close()">
                        <label class="flex w-36 items-center gap-2 rounded-lg bg-white/10 px-2.5 py-1.5 text-sm text-slate-400 ring-1 ring-transparent transition focus-within:bg-white/15 focus-within:ring-sky-400/60 sm:w-44 2xl:w-64">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" />
                            </svg>
                            <input x-ref="input" x-model="q" @input="search()" @focus="html && (open = true)"
                                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()"
                                   type="search" autocomplete="off" placeholder="タスクを探す" aria-label="タスクを探す"
                                   class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:hidden">
                            <kbd x-show="! q" class="hidden rounded border border-white/20 px-1.5 font-sans text-[11px] leading-4 text-slate-400 sm:inline">/</kbd>
                        </label>

                        <div x-show="open && q.trim() !== ''" x-cloak x-transition
                             class="absolute right-0 top-11 z-50 w-[26rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
                            <div x-ref="results" x-html="html" class="max-h-[28rem] overflow-y-auto [scrollbar-width:thin]"></div>
                        </div>
                    </div>
                    <div x-data="notifications({{ auth()->user()->unreadNotificationCount() }}, @js(auth()->user()->unreadMarks()), {{ auth()->id() }}, @js(route('notifications.index')), @js(route('notifications.read-all')))"
                         class="relative mr-1" @notifications-refresh.window="refresh()" @click.outside="bellOpen = false" @keydown.escape.window="bellOpen = false">
                        <button type="button" @click="toggle()" aria-label="通知" :aria-expanded="bellOpen ? 'true' : 'false'"
                                class="relative block rounded-lg p-2 text-slate-300 transition hover:bg-white/10 hover:text-white">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 2a6 6 0 00-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 00.515 1.076 32.91 32.91 0 003.256.508 3.5 3.5 0 006.972 0 32.903 32.903 0 003.256-.508.75.75 0 00.515-1.076A11.448 11.448 0 0116 8a6 6 0 00-6-6zM8.05 14.943a33.54 33.54 0 003.9 0 2 2 0 01-3.9 0z" />
                            </svg>
                            <span x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count" data-notification-badge
                                  class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] leading-none text-white ring-2 ring-slate-800"></span>
                        </button>

                        <div x-show="bellOpen" x-cloak x-transition
                             class="absolute right-0 top-11 z-50 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2">
                                <p class="text-sm text-slate-700">通知</p>
                                <button type="button" x-show="count > 0" @click="readAll()" class="text-xs text-sky-600 hover:text-sky-800">すべて既読</button>
                            </div>
                            <div class="max-h-96 overflow-y-auto [scrollbar-width:thin]" x-html="html"></div>
                        </div>
                    </div>
                @endunless

                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 transition hover:bg-white/10 hover:text-white">
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

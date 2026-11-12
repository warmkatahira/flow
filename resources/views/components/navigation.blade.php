@props(['hasSidebar' => false])

@php
    $user = auth()->user();

    // ナビのアイコン（heroicons mini）。メニューのタイルに出す
    $icons = [
        'home' => '<path fill-rule="evenodd" clip-rule="evenodd" d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z" />',
        'tasks' => '<path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" />',
        'archive' => '<path d="M2 3a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H2z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M2 7.5h16l-.811 7.71a2 2 0 01-1.99 1.79H4.802a2 2 0 01-1.99-1.79L2 7.5zM7 11a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" />',
        'folder' => '<path d="M3.75 3A1.75 1.75 0 002 4.75v3.26a3.235 3.235 0 011.75-.51h12.5c.644 0 1.245.188 1.75.51V6.75A1.75 1.75 0 0016.25 5h-4.836a.25.25 0 01-.177-.073L9.823 3.513A1.75 1.75 0 008.586 3H3.75zM3.75 9A1.75 1.75 0 002 10.75v4.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25v-4.5A1.75 1.75 0 0016.25 9H3.75z" />',
        'users' => '<path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z" />',
        'stack' => '<path d="M5.127 3.502L5.25 3.5h9.5c.041 0 .082 0 .123.002A2.251 2.251 0 0012.75 2h-5.5a2.25 2.25 0 00-2.123 1.502zM1 10.25A2.25 2.25 0 013.25 8h13.5A2.25 2.25 0 0119 10.25v5.5A2.25 2.25 0 0116.75 18H3.25A2.25 2.25 0 011 15.75v-5.5zM3.25 6.5c-.04 0-.082 0-.123.002A2.25 2.25 0 015.25 5h9.5c.98 0 1.814.627 2.123 1.502a3.819 3.819 0 00-.123-.002H3.25z" />',
        'profile' => '<path fill-rule="evenodd" clip-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z" />',
    ];

    // ナビに直接出すのは毎日使う2つだけ。他はすべて「すべてのメニュー」（点々のボタン）に入れる
    $pinned = [
        ['label' => 'ダッシュボード', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home'],
        ['label' => 'タスク一覧', 'route' => 'my-tasks.index', 'active' => 'my-tasks.*', 'icon' => 'tasks'],
    ];

    // 「すべてのメニュー」のグループ。メニューを増やすときはここに足す。見られる項目がないグループは出さない
    $menuGroups = [
        ['label' => 'ふだん使う', 'items' => [
            ...$pinned,
            ['label' => 'アーカイブ', 'route' => 'projects.archived', 'active' => 'projects.archived', 'icon' => 'archive'],
            ['label' => 'グループ', 'route' => 'project-groups.index', 'active' => 'project-groups.*', 'icon' => 'folder'],
        ]],
        ['label' => 'システム管理', 'items' => array_values(array_filter([
            $user->can('manage-users') ? ['label' => 'ユーザー管理', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'users'] : null,
            $user->can('browse-projects') ? ['label' => 'プロジェクト管理', 'route' => 'admin.projects.index', 'active' => 'admin.projects.*', 'icon' => 'stack'] : null,
        ]))],
        ['label' => '自分', 'items' => [
            ['label' => 'プロフィール', 'route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'profile'],
        ]],
    ];
    $menuGroups = array_values(array_filter($menuGroups, fn ($group) => $group['items'] !== []));

    // ナビに出していないメニューの画面にいるときは、点々のボタンを光らせて場所を知らせる
    $pinnedActive = collect($pinned)->contains(fn ($item) => request()->routeIs($item['active']));
    $menuActive = ! $pinnedActive && collect($menuGroups)->flatMap(fn ($group) => $group['items'])
        ->contains(fn ($item) => request()->routeIs($item['active']));
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

                {{-- すべてのメニュー --}}
                <div x-data="{ menuOpen: false }" class="relative shrink-0" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                    <button type="button" @click="menuOpen = ! menuOpen" :aria-expanded="menuOpen ? 'true' : 'false'"
                            aria-label="すべてのメニュー" title="すべてのメニュー"
                            class="grid h-9 w-9 place-items-center rounded-lg transition {{ $menuActive ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            @foreach ([4, 10, 16] as $y)
                                @foreach ([4, 10, 16] as $x)
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="1.6" />
                                @endforeach
                            @endforeach
                        </svg>
                    </button>

                    <div x-show="menuOpen" x-cloak x-transition
                         class="fixed inset-x-4 top-16 z-50 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200 sm:absolute sm:inset-x-auto sm:left-0 sm:top-12 sm:w-[36rem]">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                            <p class="text-sm font-bold text-slate-700">すべてのメニュー</p>
                            <button type="button" @click="menuOpen = false" aria-label="閉じる"
                                    class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>
                        </div>

                        <div class="max-h-[70vh] space-y-4 overflow-y-auto p-4 [scrollbar-width:thin]">
                            @foreach ($menuGroups as $group)
                                <section>
                                    <h2 class="mb-2 text-xs font-bold text-slate-400">{{ $group['label'] }}</h2>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach ($group['items'] as $item)
                                            @php($current = request()->routeIs($item['active']))
                                            <a href="{{ route($item['route']) }}" @if ($current) aria-current="page" @endif
                                               class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm transition {{ $current ? 'border-sky-300 bg-sky-50 text-sky-700' : 'border-slate-200 text-slate-700 hover:border-sky-300 hover:bg-sky-50/60' }}">
                                                <svg class="h-5 w-5 shrink-0 {{ $current ? 'text-sky-600' : 'text-slate-400' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg>
                                                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 毎日使う2つは1クリックで行けるよう、ナビに直接出す（スマホ幅では検索欄に場所を譲り、点々のメニューから行く） --}}
                <div class="hidden min-w-0 items-center gap-1 overflow-x-auto [scrollbar-width:none] sm:flex">
                    @foreach ($pinned as $item)
                        <a href="{{ route($item['route']) }}"
                           @if (request()->routeIs($item['active'])) aria-current="page" @endif
                           class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs($item['active']) ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="flex shrink-0 items-center relative">
                {{-- パスワードの変更が済むまでは他の画面へ行けないので出さない --}}
                @unless (auth()->user()->must_change_password)
                    {{-- タスクの検索。「/」でどこからでも入力できる。結果はサーバーが組み立てる（tasks.partials.search-results） --}}
                    <div x-data="taskSearch(@js(route('tasks.search')))" class="relative mr-2"
                         @click.outside="open = false" @keydown.escape.window="close()">
                        <label class="flex w-36 items-center gap-2 rounded-lg bg-white/10 px-2.5 py-1.5 text-sm text-slate-400 ring-1 ring-transparent transition focus-within:bg-white/15 focus-within:ring-sky-400/60 sm:w-52 xl:w-72">
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

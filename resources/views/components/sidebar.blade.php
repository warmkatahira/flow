{{--
    左のプロジェクトリスト。広い画面では畳める常設の列、狭い画面では開閉する引き出しになる。
    開閉は $store.sidebar（resources/js/app.js）が持つ。
--}}
<div x-data x-show="$store.sidebar.drawer" x-cloak x-transition.opacity
     @click="$store.sidebar.drawer = false"
     class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"></div>

<aside x-data x-init="$store.favorites.ids = @js($favoriteIds)"
       :data-open="$store.sidebar.drawer"
       @keydown.escape.window="$store.sidebar.drawer = false"
       aria-label="プロジェクトリスト"
       class="fixed inset-y-0 left-0 z-50 w-64 shrink-0 -translate-x-full bg-slate-800 transition-[translate,width,visibility] duration-200 max-lg:invisible data-[open=true]:translate-x-0 max-lg:data-[open=true]:visible lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:duration-[520ms] lg:ease-[cubic-bezier(.65,0,.35,1)] lg:sidebar-closed:invisible lg:sidebar-closed:w-0">
    {{-- 開閉の間だけ右端を波打たせる（resources/css/app.css） --}}
    <div class="sidebar-wave-edge" aria-hidden="true"><div class="sidebar-wave"></div></div>

    <div class="h-full overflow-hidden">
    <div class="flex h-full w-64 flex-col">
        <a href="{{ route('dashboard') }}" class="flex h-14 shrink-0 items-center border-b border-slate-700 px-4">
            <x-application-logo class="h-7" inverted />
        </a>

        <div data-sidebar-scroll class="flex-1 space-y-5 overflow-y-auto px-2 py-4 [scrollbar-width:thin]">
            @if ($personalProject)
                <section>
                    <h2 class="px-2 pb-1 text-xs font-bold text-slate-400">My Project</h2>
                    <x-sidebar-link :project="$personalProject" :current="$personalProject->id === $currentId" />
                </section>
            @endif

            <section>
                <h2 class="px-2 pb-1 text-xs font-bold text-slate-400">お気に入り</h2>

                @foreach ($projects as $project)
                    {{-- 星を押したらその場で増減させるので、全件を置いて出し分ける --}}
                    <div x-show="$store.favorites.has({{ $project->id }})"
                         @unless ($project->pivot->is_favorite) style="display: none" @endunless>
                        <x-sidebar-link :project="$project" :current="$project->id === $currentId" />
                    </div>
                @endforeach

                <p x-show="$store.favorites.ids.length === 0" x-cloak class="px-2 py-1 text-xs text-slate-500">
                    お気に入りはありません。
                </p>
            </section>

            <section>
                <h2 class="px-2 pb-1 text-xs font-bold text-slate-400">全プロジェクト</h2>

                @forelse ($sections as $groupName => $groupProjects)
                    @php
                        // 開いているプロジェクトが畳んだグループに隠れないようにする
                        $hasCurrent = $groupProjects->contains('id', $currentId);
                    @endphp

                    <div x-data="sidebarGroup(@js((string) $groupName), @js($hasCurrent))"
                         :data-closed="open ? null : ''"
                         data-sidebar-group="{{ $groupName }}"
                         @if ($hasCurrent) data-current @endif
                         class="group">
                        <button type="button" @click="toggle()" :aria-expanded="open ? 'true' : 'false'"
                                class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm font-semibold text-slate-200 transition hover:bg-slate-700">
                            <svg class="h-5 w-5 shrink-0 text-sky-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M2 5.5A1.5 1.5 0 013.5 4h4.38a1.5 1.5 0 011.06.44L10.5 6h6A1.5 1.5 0 0118 7.5v7a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 012 14.5v-9z" />
                            </svg>
                            <span class="min-w-0 flex-1 truncate">{{ $groupName === '' ? 'グループなし' : $groupName }}</span>
                            <svg class="h-4 w-4 shrink-0 rotate-90 text-slate-400 transition-transform duration-300 group-data-[closed]:rotate-0"
                                 viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                      d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" />
                            </svg>
                        </button>

                        {{-- x-collapse は高さを 0 まで畳むので、余白は内側の要素に持たせる --}}
                        <div data-sidebar-group-body x-show="open" x-collapse.duration.300ms>
                            <div class="pl-3">
                                @forelse ($groupProjects as $project)
                                    <x-sidebar-link :project="$project" :current="$project->id === $currentId" />
                                @empty
                                    <p class="px-2 py-1 text-xs text-slate-500">プロジェクトはありません。</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="px-2 py-1 text-xs text-slate-500">プロジェクトはありません。</p>
                @endforelse
            </section>
        </div>
    </div>
    </div>
</aside>

{{-- 画面遷移のたびにリストが一瞬ずれないよう、描画の前に畳んだグループとスクロール位置を戻す --}}
<script>
    (() => {
        try {
            const closed = JSON.parse(localStorage.getItem('flow.sidebar.closedGroups') ?? '[]');
            const tree = document.querySelector('[data-sidebar-scroll]');

            tree.querySelectorAll('[data-sidebar-group]:not([data-current])').forEach((group) => {
                if (closed.includes(group.dataset.sidebarGroup)) {
                    group.dataset.closed = '';
                    group.querySelector('[data-sidebar-group-body]').style.display = 'none';
                }
            });

            tree.scrollTop = Number(sessionStorage.getItem('flow.sidebar.scroll')) || 0;
        } catch {
            // ブラウザの保存領域が使えなくても、全部開いた状態で表示できる
        }
    })();
</script>

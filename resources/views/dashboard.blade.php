<x-layouts.app title="ダッシュボード">
    <x-slot:header>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-bold text-slate-800">ダッシュボード</h1>

            <div class="flex items-center gap-2">
                <a href="{{ route('project-groups.create') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    グループを作成
                </a>
                <a href="{{ route('projects.create') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                    プロジェクトを作成
                </a>
            </div>
        </div>
    </x-slot:header>

    <div class="px-4 sm:px-6 lg:px-8 py-8">
        {{-- 自分専用の My Project。お気に入りやグループには混ぜず、いつもいちばん上に置く --}}
        @if ($personalProject)
            <section class="mb-8">
                <h2 class="mb-3 text-sm font-bold text-slate-500">My Project</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-project-card :project="$personalProject" class="h-full" />
                </div>
            </section>
        @endif

        @if ($projects->isEmpty())
            <h2 class="mb-4 text-sm font-bold text-slate-500">参加しているプロジェクト</h2>

            <div class="rounded-xl bg-white p-10 text-center shadow-sm">
                <p class="font-medium text-slate-700">参加しているプロジェクトはありません。</p>
                <p class="mt-2 text-sm text-slate-500">
                    プロジェクトを作成するか、オーナーに参加者として追加してもらってください。
                </p>
                <a href="{{ route('projects.create') }}"
                   class="mt-5 inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                    プロジェクトを作成
                </a>
            </div>
        @else
            {{--
                参加しているプロジェクトは全件描画済みなので、絞り込みは画面側で完結させる。
                入力するたびにサーバーへ行かないぶん、すぐ反応する。
            --}}
            <div x-data="{
                    keyword: '',
                    names: @js($projects->pluck('name')->values()),
                    all: @js($projects->map(fn ($project) => ['id' => $project->id, 'name' => $project->name])->values()),
                    normalized() {
                        return this.keyword.trim().toLowerCase();
                    },
                    matches(name) {
                        return this.normalized() === '' || name.toLowerCase().includes(this.normalized());
                    },
                    hitsIn(names) {
                        return names.filter((name) => this.matches(name)).length;
                    },
                    get hitCount() {
                        return this.hitsIn(this.names);
                    },
                    get favoriteCount() {
                        return this.all.filter((project) => this.$store.favorites.has(project.id)).length;
                    },
                    get favoriteHits() {
                        return this.all.filter((project) => this.$store.favorites.has(project.id) && this.matches(project.name)).length;
                    },
                 }"
                 x-init="$store.favorites.ids = @js($favoriteIds)">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-500">
                        参加しているプロジェクト
                        <span class="ml-1 font-normal text-slate-400"
                              x-text="normalized() === '' ? '{{ $projects->count() }}件' : hitCount + '件'"></span>
                    </h2>

                    <div class="relative w-full sm:w-72">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                  d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a1 1 0 01-1.42 1.42l-3.08-3.08A7 7 0 012 9z" />
                        </svg>

                        {{-- ブラウザ標準のクリアボタンは自前のものと二重になるので消す --}}
                        <input autocomplete="off" type="search" x-model="keyword" placeholder="プロジェクト名で検索"
                               aria-label="プロジェクト名で検索"
                               class="block w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-800 shadow-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none [&::-webkit-search-cancel-button]:appearance-none">

                        <button type="button" x-show="keyword !== ''" x-cloak @click="keyword = ''"
                                aria-label="検索条件をクリア"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{--
                    お気に入り。星を押した瞬間にここへ入る／出ていくよう、全プロジェクトを
                    描画しておいて store の状態で出し分ける。
                --}}
                <div x-cloak class="mb-6">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700">
                        <svg class="h-4 w-4 fill-amber-400 text-amber-400" viewBox="0 0 20 20"
                             stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linejoin="round"
                                  d="M10 2.5l2.35 4.76 5.25.77-3.8 3.7.9 5.23L10 14.49l-4.7 2.47.9-5.23-3.8-3.7 5.25-.77L10 2.5z" />
                        </svg>
                        お気に入り
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-show="favoriteHits > 0">
                        {{--
                            x-show はコンポーネントの属性として渡すと Blade が @js() を展開しないので、
                            必ず外側の div に付ける。
                        --}}
                        @foreach ($projects as $project)
                            <div x-show="$store.favorites.has({{ $project->id }}) && matches(@js($project->name))">
                                <x-project-card :project="$project" :groups="$groups" class="h-full" />
                            </div>
                        @endforeach
                    </div>

                    {{-- お気に入りが0件でも枠は残す --}}
                    <div x-show="favoriteHits === 0"
                         class="rounded-xl border border-dashed border-slate-300 px-5 py-6 text-center text-sm text-slate-500">
                        <span x-show="favoriteCount === 0">お気に入りはありません。</span>
                        <span x-show="favoriteCount > 0">一致するお気に入りはありません。</span>
                    </div>
                </div>

                {{-- グループどうしは隙間なく積む。見出しのバーが区切りになる --}}
                <div class="overflow-hidden rounded-xl border border-slate-200" x-show="hitCount > 0">
                    @foreach ($sections as $groupName => $groupProjects)
                        {{--
                            絞り込みで中身が全部消えたグループは見出しごと隠す。件数も絞り込みに連動させる。
                            開閉状態は localStorage に覚えておく。検索中は畳んでいても中身を出す
                            （畳んだグループの中にヒットがあると見つけられなくなるため）。
                        --}}
                        <section x-data="{
                                    groupNames: @js($groupProjects->pluck('name')->values()),
                                    storageKey: @js('flow.dashboard.group.'.($groupName === '' ? '__none__' : $groupName)),
                                    open: true,
                                    init() {
                                        const saved = localStorage.getItem(this.storageKey);
                                        if (saved !== null) {
                                            this.open = saved === '1';
                                        }
                                    },
                                    toggle() {
                                        this.open = ! this.open;
                                        localStorage.setItem(this.storageKey, this.open ? '1' : '0');
                                    },
                                 }"
                                 x-show="normalized() === '' || hitsIn(groupNames) > 0">
                            <button type="button" @click="toggle()"
                                    :aria-expanded="(open || normalized() !== '') ? 'true' : 'false'"
                                    class="flex w-full items-center gap-2 border-b border-slate-300/70 bg-slate-200/70 px-4 py-2.5 text-left text-sm font-bold transition hover:bg-slate-200 {{ $groupName === '' ? 'text-slate-500' : 'text-slate-700' }}">
                                <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300"
                                     :class="(open || normalized() !== '') && 'rotate-90'"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                          d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" />
                                </svg>

                                @if ($groupName === '')
                                    グループなし
                                @else
                                    <span class="inline-block h-3 w-1 rounded-full bg-sky-500"></span>
                                    {{ $groupName }}
                                @endif
                                <span class="font-normal text-slate-400" x-text="hitsIn(groupNames) + '件'"></span>
                            </button>

                            {{-- x-collapse は高さを 0 まで畳むので、余白は内側の要素に持たせる --}}
                            <div x-show="open || normalized() !== ''" x-collapse.duration.300ms>
                                <div class="bg-slate-50 px-4 py-4">
                                    @if ($groupProjects->isEmpty())
                                        <p class="text-xs text-slate-400">
                                            このグループにプロジェクトはありません。カードの ⋯ から入れられます。
                                        </p>
                                    @else
                                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($groupProjects as $project)
                                                <div x-show="matches(@js($project->name))">
                                                    <x-project-card :project="$project" :groups="$groups" class="h-full" />
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endforeach
                </div>

                <div x-show="hitCount === 0" x-cloak class="rounded-xl bg-white p-10 text-center shadow-sm">
                    <p class="text-sm text-slate-500">
                        「<span class="font-medium text-slate-700" x-text="keyword"></span>」に一致するプロジェクトはありません。
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>

{{--
    プロジェクト内のタブ（プロジェクトの画面と編集画面で共通）

    プロジェクトの画面では Alpine の tab を切り替えるボタン、編集画面（$active = 'settings'）では
    プロジェクトの画面の該当タブへのリンクにする。
--}}
@php
    use App\Enums\ProjectRole;

    $active ??= null;
    $tabClass = 'inline-flex shrink-0 cursor-pointer items-center gap-1.5 border-b-2 px-4 py-3 text-sm font-semibold transition';
    $idleClass = 'border-transparent text-slate-500 hover:text-slate-800';
    $activeClass = 'border-sky-600 text-sky-700';
    $tabs = [
        'board' => ['ボード', '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M14 17h2.75A2.25 2.25 0 0019 14.75V5.25A2.25 2.25 0 0016.75 3H14v14zM12.5 3h-5v14h5V3zM3.25 3H6v14H3.25A2.25 2.25 0 011 14.75v-9.5A2.25 2.25 0 013.25 3z" /></svg>'],
        // キーは URL（?tab=calendar）とブラウザに覚えた表示で使っているので calendar のまま。中でカレンダーとタイムラインを切り替える
        'calendar' => ['スケジュール', '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><rect x="2" y="3.5" width="9" height="3" rx="1.5" /><rect x="6" y="8.5" width="10" height="3" rx="1.5" /><rect x="10" y="13.5" width="8" height="3" rx="1.5" /></svg>'],
        'files' => ['ファイル', '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z" /></svg>'],
        'members' => ['メンバー', '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z" /></svg>'],
        'archive' => ['アーカイブ', '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 3a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H2z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M2 7.5h16l-.811 7.71a2 2 0 01-1.99 1.79H4.802a2 2 0 01-1.99-1.79L2 7.5zM7 11a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" /></svg>'],
    ];
@endphp

<nav class="flex min-w-0 gap-1 overflow-x-auto [scrollbar-width:none]" aria-label="プロジェクトのタブ">
    @foreach ($tabs as $key => [$label, $icon])
        @if ($active)
            <a href="{{ route('projects.show', [$project, 'tab' => $key]) }}" class="{{ $tabClass }} {{ $idleClass }}">
                {!! $icon !!}
                {{ $label }}
                @if ($key === 'archive' && isset($archiveCount))
                    <span class="ml-0.5 font-normal text-slate-400">{{ $archiveCount }}</span>
                @endif
            </a>
        @else
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? '{{ $activeClass }}' : '{{ $idleClass }}'"
                    class="{{ $tabClass }}">
                {!! $icon !!}
                {{ $label }}
                @if ($key === 'archive' && isset($archiveCount))
                    <span data-live-archive-count class="ml-0.5 font-normal text-slate-400">{{ $archiveCount }}</span>
                @endif
            </button>
        @endif
    @endforeach

    @if ($project->roleOf(auth()->user()) === ProjectRole::Owner && ! $project->isArchived())
        <a href="{{ route('projects.edit', $project) }}" @if ($active === 'settings') aria-current="page" @endif
           class="{{ $tabClass }} {{ $active === 'settings' ? $activeClass : $idleClass }}">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" /></svg>
            設定
        </a>
    @endif
</nav>

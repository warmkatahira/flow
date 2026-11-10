@props(['project', 'groups' => null])

@php
    $me = auth()->user();
    $role = $project->roleOf($me);
    $currentGroupId = $project->pivot->group_id ?? null;
@endphp

{{--
    カード全体をリンクにしつつ、星とグループのメニューは別に押せるようにする。
    リンクを入れ子にできないので、擬似要素をカード全面に敷いて上に重ねる。
--}}
<div {{ $attributes->merge(['class' => 'project-card relative isolate flex items-start gap-4 rounded-xl bg-white p-5 shadow-sm transition hover:shadow-md']) }}>
    {{-- ホバーで水が満ちて魚が泳ぐ。⋯ のメニューまで切れないよう、切り抜くのはこの層だけにする --}}
    <div class="project-card-water-layer" aria-hidden="true">
        <div class="project-card-water"></div>
        <span class="project-card-bubble"></span>
        <span class="project-card-bubble"></span>
        <span class="project-card-bubble"></span>
        <svg class="project-card-fish" viewBox="0 0 32 18">
            <path d="M5 9C11 1.5 22 1.5 28 9 22 16.5 11 16.5 5 9Z" />
            <path d="M7 9 0 3v12Z" />
            <circle cx="23" cy="7.5" r="1.4" class="fill-slate-900" />
        </svg>
    </div>

    <x-project-icon :project="$project" />

    <div class="min-w-0 flex-1">
        <a href="{{ route('projects.show', $project) }}"
           class="block truncate font-semibold text-slate-800 after:absolute after:inset-0 hover:text-sky-700">
            {{ $project->name }}
        </a>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($project->isPersonal())
                <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">My Project</span>
                <span class="text-xs text-slate-500">自分専用</span>
            @elseif ($role)
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $role->badgeClass() }}">
                    {{ $role->label() }}
                </span>
            @endif
            @unless ($project->isPersonal())
                <span class="text-xs text-slate-500">参加者 {{ $project->users_count ?? $project->users->count() }}人</span>
            @endunless
        </div>
    </div>

    <div class="relative z-10 flex shrink-0 items-center gap-0.5">
        {{-- My Project はいつもいちばん上にあるので、お気に入りやグループには入れない --}}
        @unless ($project->isPersonal())
            <x-favorite-button :project="$project" class="-mt-1" />
        @endunless

        @if ($groups !== null && ! $project->isPersonal())
            {{-- グループの割り当て。自分のダッシュボードでの分類なので他の人には影響しない --}}
            <div x-data="{ open: false }" class="relative -mt-1">
                <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                        aria-label="グループを変更"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM11.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM17 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                </button>

                <div x-show="open" x-cloak @click.outside="open = false" x-transition
                     class="absolute right-0 top-9 z-20 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    <p class="px-3 py-1 text-[10px] font-bold text-slate-400">グループに入れる</p>

                    <form autocomplete="off" method="POST" action="{{ route('projects.group.update', $project) }}">
                        @csrf
                        @method('PUT')

                        <button type="submit" name="group_id" value=""
                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm transition hover:bg-slate-100 {{ $currentGroupId === null ? 'font-semibold text-sky-700' : 'text-slate-700' }}">
                            <span class="w-3 text-xs">{{ $currentGroupId === null ? '✓' : '' }}</span>
                            グループなし
                        </button>

                        @foreach ($groups as $group)
                            <button type="submit" name="group_id" value="{{ $group->id }}"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm transition hover:bg-slate-100 {{ $currentGroupId === $group->id ? 'font-semibold text-sky-700' : 'text-slate-700' }}">
                                <span class="w-3 text-xs">{{ $currentGroupId === $group->id ? '✓' : '' }}</span>
                                <span class="min-w-0 truncate">{{ $group->name }}</span>
                            </button>
                        @endforeach
                    </form>

                    @if ($groups->isEmpty())
                        <a href="{{ route('project-groups.create') }}"
                           class="block border-t border-slate-100 px-3 py-1.5 text-sm text-sky-600 hover:bg-slate-100">
                            グループを作成
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

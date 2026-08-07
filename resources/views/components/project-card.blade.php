@props(['project'])

@php
    $role = $project->roleOf(auth()->user());
@endphp

{{--
    カード全体をリンクにしつつ、お気に入りの星だけは別に押せるようにする。
    リンクを入れ子にできないので、擬似要素をカード全面に敷いて star を上に置く。
--}}
<div {{ $attributes->merge(['class' => 'relative flex items-start gap-4 rounded-xl bg-white p-5 shadow-sm transition hover:shadow-md']) }}>
    <x-project-icon :project="$project" />

    <div class="min-w-0 flex-1">
        <a href="{{ route('projects.show', $project) }}"
           class="block truncate font-semibold text-slate-800 after:absolute after:inset-0 hover:text-sky-700">
            {{ $project->name }}
        </a>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($role)
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $role->badgeClass() }}">
                    {{ $role->label() }}
                </span>
            @endif
            <span class="text-xs text-slate-500">参加者 {{ $project->users_count ?? $project->users->count() }}人</span>
        </div>
    </div>

    <x-favorite-button :project="$project" class="relative z-10 -mr-1 -mt-1" />
</div>

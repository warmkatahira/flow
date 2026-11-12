@props(['project', 'active' => null, 'archiveCount' => null])

{{--
    プロジェクトの画面と設定画面の上の帯。背景から少し浮いたカプセルに、プロジェクト名・タブ・道具（スロット）を載せる。
    プロジェクト名は左のリストにもあるので、場所に余裕のある広い画面（xl）でだけ出す。
--}}
<div class="shrink-0 bg-gradient-to-b from-sky-100/80 to-slate-100 px-4 pt-3 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/85 p-1.5 shadow-[0_8px_24px_-12px_rgb(2_132_199/0.35)] ring-1 ring-slate-900/5 backdrop-blur">
        <div class="flex min-w-0 items-center gap-2">
            <div class="hidden min-w-0 max-w-64 shrink-0 items-center gap-2 border-r border-slate-200 pl-1.5 pr-3 xl:flex">
                <x-project-icon :project="$project" size="xs" class="!rounded-lg" />
                <span class="truncate text-sm font-bold text-slate-800" title="{{ $project->name }}">{{ $project->name }}</span>
            </div>

            @include('projects.partials.tabs', ['project' => $project, 'active' => $active, 'archiveCount' => $archiveCount])
        </div>

        @if ($slot->isNotEmpty())
            <div class="flex shrink-0 items-center gap-1.5">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>

{{--
    アーカイブ中のプロジェクトのボード。見るだけなので操作の UI は持たない
    （システム管理者の My Project の閲覧でも使う。$taskUrl を渡すとカードを押して詳細を見られる）
--}}
@php
    $taskUrl ??= null;
@endphp
<div class="flex min-h-0 flex-1 gap-4 overflow-x-auto pb-3 pr-4 sm:pr-6 lg:pr-8 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]" x-drag-scroll>
    @forelse ($project->taskLists as $list)
        <section class="flex min-h-0 w-72 shrink-0 flex-col rounded-xl bg-slate-200/70 shadow-sm">
            <div class="h-2 shrink-0 rounded-t-xl" style="background-color: {{ $list->color }}"></div>

            <div class="flex shrink-0 items-center gap-1.5 px-3 pb-1 pt-2">
                <span class="min-w-0 truncate text-sm text-slate-900">{{ $list->name }}</span>
                <span class="shrink-0 text-sm text-sky-600">{{ $list->tasks->count() }}</span>
            </div>

            <div class="min-h-8 flex-1 space-y-2 overflow-y-auto p-2 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]">
                @foreach ($list->tasks as $task)
                    @if ($taskUrl)<a href="{{ $taskUrl($task) }}" class="block rounded-lg transition hover:-translate-y-0.5 hover:shadow-md">@endif
                    <article data-status="{{ $task->status->value }}" data-due="{{ $task->ended_on?->format('Y-m-d') }}"
                             data-assignees="{{ $task->assignees->pluck('id')->join(',') }}"
                             data-labels="{{ $task->labels->pluck('id')->join(',') }}"
                             data-followed="{{ $followedTaskIds->contains($task->id) ? '1' : '' }}"
                             class="flex items-start gap-2 rounded-lg bg-white p-2.5 shadow-xs ring-1 ring-slate-200">
                        <div class="flex shrink-0 flex-col items-center gap-1">
                            <span class="text-[10px] font-medium leading-4 text-slate-400">#{{ $task->number }}</span>
                            <x-task-status-icon :status="$task->status" class="h-4 w-4" />
                            @if ($followedTaskIds->contains($task->id))
                                <x-follow-icon class="h-3.5 w-3.5 text-sky-500" title="フォロー中" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-800">{{ $task->title }}</p>
                            @include('projects.partials.task-label-bars')
                            <x-due-chip :task="$task" />
                        </div>
                    </article>
                    @if ($taskUrl)</a>@endif
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-sm text-slate-500">リストはありません。</p>
    @endforelse
</div>

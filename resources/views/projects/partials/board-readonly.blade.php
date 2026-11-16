{{--
    アーカイブ中のプロジェクトのボード。見るだけなので操作の UI は持たない
    （システム管理者の My Project の閲覧でも使う。$taskUrl を渡すとカードを押して詳細を見られる）
--}}
@php
    $taskUrl ??= null;
@endphp
<div class="note-board flex min-h-0 flex-1 gap-6 overflow-x-auto pb-3 pr-4 sm:pr-6 lg:pr-8 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]" x-drag-scroll>
    @forelse ($project->taskLists as $list)
        {{-- 見た目はボードと同じ付箋（resources/css/app.css の .note-board） --}}
        <section style="--lc: {{ $list->color }}" class="flex min-h-0 w-72 shrink-0 flex-col">
            <div class="shrink-0 px-2">
                <div class="note-list-head flex items-center gap-1.5 px-0.5 pb-1.5 pt-0.5">
                    <span class="min-w-0 truncate text-[15px] text-slate-900">{{ $list->name }}</span>
                    <span class="shrink-0 text-xs text-slate-500">{{ $list->tasks->count() }}</span>
                </div>
            </div>

            <div class="min-h-8 flex-1 space-y-3.5 overflow-y-auto px-2 pb-2 pt-4 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]">
                @foreach ($list->tasks as $task)
                    @if ($taskUrl)<a href="{{ $taskUrl($task) }}" class="block transition hover:-translate-y-0.5">@endif
                    <article data-status="{{ $task->status->value }}" data-due="{{ $task->ended_on?->format('Y-m-d') }}"
                             data-assignees="{{ $task->assignees->pluck('id')->join(',') }}"
                             data-labels="{{ $task->labels->pluck('id')->join(',') }}"
                             data-followed="{{ $followedTaskIds->contains($task->id) ? '1' : '' }}"
                             class="note-card relative flex items-start gap-2 p-2.5">
                        <div class="flex shrink-0 flex-col items-center gap-1">
                            <span class="note-num text-[10px] font-medium leading-4">#{{ $task->number }}</span>
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

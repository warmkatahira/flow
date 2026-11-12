{{-- タスクの検索結果（ナビの検索欄が x-html で差し込む。上下キーで選ぶ行に data-search-item を付ける） --}}
@if ($tasks->isEmpty())
    @if ($searched)
        <p class="px-4 py-6 text-center text-sm text-slate-400">見つかりませんでした</p>
    @endif
@else
    <ul class="py-1">
        @foreach ($tasks as $task)
            <li>
                <a href="{{ route('projects.show', [$task->project, 'task' => $task->getKey()]) }}" data-search-item
                   class="flex items-center gap-3 px-3 py-2 transition hover:bg-slate-50 data-[active=true]:bg-sky-50">
                    <x-task-status-icon :status="$task->status" class="h-4 w-4" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm text-slate-800">
                            <span class="mr-1 text-xs text-slate-400">#{{ $task->number }}</span>{{ $task->title }}
                        </span>
                        <span class="block truncate text-xs text-slate-400">{{ $task->project->name }} / {{ $task->list->name }}</span>
                    </span>
                    {{-- 期限の印はカード用に上の余白を持っているので打ち消す --}}
                    <span class="shrink-0 [&>[data-due-chip]]:mt-0"><x-due-chip :task="$task" /></span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($tasks->count() >= \App\Http\Controllers\Task\TaskSearchController::LIMIT)
        <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">{{ \App\Http\Controllers\Task\TaskSearchController::LIMIT }} 件まで出しています。言葉を足して絞り込んでください</p>
    @endif
@endif

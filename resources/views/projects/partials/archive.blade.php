{{-- プロジェクト内のアーカイブ。ボードから外したリストとタスクを元に戻せる --}}
<div class="max-w-3xl space-y-6">
    @if ($archivedLists->isEmpty() && $archivedTasks->isEmpty())
        <p class="rounded-xl bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
            アーカイブしたリストやタスクはありません。
        </p>
    @endif

    @if ($archivedLists->isNotEmpty())
        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-bold text-slate-700">
                リスト（{{ $archivedLists->count() }}件）
            </h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($archivedLists as $list)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-800">{{ $list->name }}</p>
                            <p class="text-xs text-slate-500">
                                タスク {{ $list->tasks_count }}件・{{ $list->archived_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }} にアーカイブ
                            </p>
                        </div>
                        @unless ($project->isArchived())
                            <form autocomplete="off" method="POST" action="{{ route('lists.unarchive', $list) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-sky-700 hover:text-sky-900">元に戻す</button>
                            </form>
                            <form autocomplete="off" method="POST" action="{{ route('lists.destroy', $list) }}"
                                  onsubmit="return confirm(@js($list->name.' を完全に削除します。中のタスクもすべて消え、元に戻せません。よろしいですか？'));">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">完全に削除</button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($archivedTasks->isNotEmpty())
        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-bold text-slate-700">
                タスク（{{ $archivedTasks->count() }}件）
            </h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($archivedTasks as $task)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="shrink-0 text-xs font-medium text-slate-400">#{{ $task->number }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-800">{{ $task->title }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $task->list->name }}・{{ $task->archived_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }} にアーカイブ
                            </p>
                        </div>
                        @unless ($project->isArchived())
                            <form autocomplete="off" method="POST" action="{{ route('tasks.unarchive', $task) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-sky-700 hover:text-sky-900">元に戻す</button>
                            </form>
                            <form autocomplete="off" method="POST" action="{{ route('tasks.destroy', $task) }}"
                                  onsubmit="return confirm(@js('#'.$task->number.' を完全に削除します。元に戻せません。よろしいですか？'));">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">完全に削除</button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

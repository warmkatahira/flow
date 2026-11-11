{{--
    カンバン本体。リストもタスクもドラッグで並べ替えられる（resources/js/app.js の x-board）。
    並べ替え以外の操作は素直にフォーム送信する。
--}}
@if ($project->taskLists->isEmpty())
    <p class="mb-4 text-sm text-slate-500">
        リストを追加してカンバンを作りましょう。ToDo / 進行中 / 完了 のように並べます。
    </p>
@endif

{{-- リストは画面の下まで伸ばし、タスクが多いときはリストの中でスクロールする --}}
<div class="flex min-h-0 flex-1 gap-4 overflow-x-auto pb-3 pr-4 sm:pr-6 lg:pr-8 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]"
     x-drag-scroll
     x-board="{
        tasksUrl: @js(route('tasks.reorder', $project)),
        listsUrl: @js(route('lists.reorder', $project)),
        archiveUrl: @js(route('tasks.archive-many', $project)),
     }">
    @foreach ($project->taskLists as $list)
        <section data-list-id="{{ $list->id }}" x-data="{ adding: false }"
                 class="flex min-h-0 w-72 shrink-0 flex-col rounded-xl bg-slate-200/70 shadow-sm">
            {{-- 上端の帯。見出しと同じくリストを掴める --}}
            <div data-list-handle class="h-2 shrink-0 cursor-grab rounded-t-xl" style="background-color: {{ $list->color }}"></div>

            {{-- 見出し --}}
            <div class="shrink-0">
                <div data-list-handle class="flex cursor-grab items-center gap-1.5 px-3 pb-1 pt-2">
                    <span class="min-w-0 truncate text-sm text-slate-900">{{ $list->name }}</span>
                    <span data-list-count class="shrink-0 text-sm text-sky-600">{{ $list->tasks->count() }}</span>

                    <x-board-menu title="リストメニュー" class="ml-auto" button-class="p-1 text-slate-700 hover:bg-black/10">
                        <x-board-menu-item @click="menuOpen = false; adding = true; $nextTick(() => $refs.title.focus())">新規タスクを追加</x-board-menu-item>

                        <div class="my-1.5 border-t border-slate-100"></div>

                        <button type="button" role="menuitem"
                                @click="menuOpen = false; $dispatch('open-list-edit', { name: @js($list->name), color: @js($list->color), action: @js(route('lists.update', $list)) })"
                                class="block w-full px-4 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                            リストを編集
                        </button>
                        <form autocomplete="off" method="POST" action="{{ route('lists.duplicate', $list) }}">
                            @csrf
                            <x-board-menu-item type="submit">リストを複製</x-board-menu-item>
                        </form>

                        <div class="my-1.5 border-t border-slate-100"></div>

                        <form autocomplete="off" method="POST" action="{{ route('lists.archive', $list) }}"
                              data-archive-confirm="リスト" data-name="{{ $list->name }}" data-note="中のタスクも一緒にボードから消えます。">
                            @csrf
                            <x-board-menu-item type="submit">リストをアーカイブ</x-board-menu-item>
                        </form>
                    </x-board-menu>
                </div>
            </div>

            {{-- タスクを追加 --}}
            <div class="shrink-0 px-2 pt-2">
                <button type="button" @click="adding = true; $nextTick(() => $refs.title.focus())" x-show="! adding"
                        class="flex w-full items-center justify-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm text-sky-600 transition hover:bg-sky-50">
                    ＋ タスクを追加
                </button>

                <form autocomplete="off" method="POST" action="{{ route('tasks.store', $list) }}" x-show="adding" x-cloak class="space-y-2">
                    @csrf
                    <input autocomplete="off" type="text" name="title" x-ref="title" required maxlength="255" placeholder="タスク名"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
                    <div class="flex items-center gap-2">
                        <button type="submit" class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-semibold text-white hover:bg-sky-700">追加</button>
                        <button type="button" @click="adding = false" class="text-xs text-slate-600 hover:text-slate-900">やめる</button>
                    </div>
                </form>
            </div>

            {{-- タスク --}}
            <div data-tasks="{{ $list->id }}" class="min-h-8 flex-1 space-y-2 overflow-y-auto p-2 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]">
                @foreach ($list->tasks as $task)
                    <article data-task-id="{{ $task->id }}" data-task-url="{{ route('tasks.show', $task) }}"
                             data-status="{{ $task->status->value }}" data-due="{{ $task->ended_on?->format('Y-m-d') }}"
                             data-assignees="{{ $task->assignees->pluck('id')->join(',') }}"
                             data-labels="{{ $task->labels->pluck('id')->join(',') }}"
                             data-followed="{{ $followedTaskIds->contains($task->id) ? '1' : '' }}"
                             class="task-card cursor-pointer rounded-lg bg-white p-2.5 shadow-xs ring-1 ring-slate-200">
                        <div class="flex items-start gap-2">
                            <div class="flex shrink-0 flex-col items-center gap-1">
                                <span class="text-[10px] font-medium leading-4 text-slate-400">#{{ $task->number }}</span>
                                <x-task-status-icon :status="$task->status" class="h-4 w-4" />
                                @if ($followedTaskIds->contains($task->id))
                                    <x-follow-icon class="h-3.5 w-3.5 text-sky-500" title="フォロー中" />
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm text-slate-800">{{ $task->title }}</p>

                                @include('projects.partials.task-label-bars')

                                <x-due-chip :task="$task" />

                                {{-- 詳細は「拡張する」で出す --}}
                                @if ($task->description)
                                    <p class="mt-1 line-clamp-2 hidden break-words text-xs text-slate-500 cards-expanded:[display:-webkit-box]">{{ $task->descriptionText() }}</p>
                                @endif

                                @if ($task->started_on || $task->ended_on || $task->checklist_items_count || $task->assignees->isNotEmpty())
                                    <div class="mt-1.5 hidden flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-500 cards-expanded:flex">
                                        @if ($task->started_on || $task->ended_on)
                                            <span>{{ $task->started_on?->format('n/j') }}〜{{ $task->ended_on?->format('n/j') }}</span>
                                        @endif
                                        @if ($task->checklist_items_count)
                                            <span title="チェックリスト">☑ {{ $task->done_checklist_items_count }}/{{ $task->checklist_items_count }}</span>
                                        @endif
                                        @if ($task->assignees->isNotEmpty())
                                            <span class="ml-auto flex -space-x-1.5">
                                                @foreach ($task->assignees as $assignee)
                                                    <x-user-avatar :user="$assignee" size="xxs" class="ring-2 ring-white" title="{{ $assignee->name }}" />
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <x-board-menu title="タスクメニュー" button-class="p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                <button type="button" role="menuitem"
                                        @click="menuOpen = false; $dispatch('open-task', { url: @js(route('tasks.show', $task)) })"
                                        class="block w-full px-4 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                                    タスクを開く
                                </button>

                                <div class="my-1.5 border-t border-slate-100"></div>

                                <form autocomplete="off" method="POST" action="{{ route('tasks.duplicate', $task) }}">
                                    @csrf
                                    <x-board-menu-item type="submit">タスクを複製</x-board-menu-item>
                                </form>
                                <button type="button" role="menuitem"
                                        @click="menuOpen = false; $dispatch('open-move', { name: @js($task->title), action: @js(route('tasks.move', $task)), listId: {{ $list->id }} })"
                                        class="block w-full px-4 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                                    タスクを移動
                                </button>

                                <div class="my-1.5 border-t border-slate-100"></div>

                                <form autocomplete="off" method="POST" action="{{ route('tasks.archive', $task) }}"
                                      data-archive-confirm="タスク" data-name="{{ $task->title }}">
                                    @csrf
                                    <x-board-menu-item type="submit">タスクをアーカイブ</x-board-menu-item>
                                </form>
                            </x-board-menu>
                        </div>
                    </article>
                @endforeach
            </div>

        </section>
    @endforeach

    {{-- リストを追加 --}}
    <div class="w-72 shrink-0 self-start" x-data="{ adding: false }">
        <button type="button" @click="adding = true; $nextTick(() => $refs.name.focus())" x-show="! adding"
                class="flex w-full items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-white/60 px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-white">
            ＋ リストを追加
        </button>

        <form autocomplete="off" method="POST" action="{{ route('lists.store', $project) }}" x-show="adding" x-cloak
              class="space-y-2 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
            @csrf
            <input autocomplete="off" type="text" name="name" x-ref="name" required maxlength="255" placeholder="リスト名"
                   class="block w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">

            <x-list-color-picker />

            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-semibold text-white hover:bg-sky-700">追加</button>
                <button type="button" @click="adding = false" class="text-xs text-slate-600 hover:text-slate-900">やめる</button>
            </div>
        </form>
    </div>
</div>

{{--
    ドラッグ中だけボードの下に出る。ここに落としたタスクをまとめてアーカイブする（resources/js/app.js の x-board）
    リストに重ねるとリストの末尾への移動と取り合ってちらつくので、重ねずにボードの下へ並べる（出ている間はリストが縮む）
--}}
<div data-archive-zone
     class="relative mb-3 mr-4 hidden h-16 shrink-0 animate-flow-rise items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-400 bg-slate-900/85 px-5 text-sm text-white shadow-lg sm:mr-6 lg:mr-8 transition-colors duration-200 [&:not(.hidden)]:flex has-[[data-task-id]]:border-sky-200 has-[[data-task-id]]:bg-sky-500 [&_[data-task-id]]:absolute [&_[data-task-id]]:opacity-0">
    <span class="pointer-events-none flex items-center justify-center gap-2.5">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M2 3a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H2z" />
            <path fill-rule="evenodd" clip-rule="evenodd" d="M2 7.5h16l-.811 7.71a2 2 0 01-1.99 1.79H4.802a2 2 0 01-1.99-1.79L2 7.5zM7 11a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" />
        </svg>
        ここにドロップしてアーカイブ
    </span>
</div>

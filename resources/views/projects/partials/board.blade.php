{{--
    カンバン本体。リストもタスクもドラッグで並べ替えられる（resources/js/app.js の x-board）。
    並べ替え以外の操作は素直にフォーム送信する。
--}}
@if ($project->taskLists->isEmpty())
    <p class="mb-4 text-sm text-slate-500">
        リストを追加してカンバンを作りましょう。ToDo / 進行中 / 完了 のように並べます。
    </p>
@endif

{{-- ドラッグ中だけ出る。ここに落としたタスクをまとめてアーカイブする（resources/js/app.js の x-board） --}}
<div data-archive-zone
     class="hidden fixed bottom-6 left-1/2 z-40 w-80 -translate-x-1/2 animate-flow-rise overflow-hidden rounded-2xl bg-slate-900/85 px-5 py-4 text-sm text-white shadow-2xl ring-1 ring-white/10 backdrop-blur-md transition-[background-color,scale] duration-200 has-[[data-task-id]]:scale-105 has-[[data-task-id]]:bg-sky-500 [&_[data-task-id]]:absolute [&_[data-task-id]]:opacity-0">
    <span class="pointer-events-none flex items-center justify-center gap-2.5">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M2 3a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H2z" />
            <path fill-rule="evenodd" clip-rule="evenodd" d="M2 7.5h16l-.811 7.71a2 2 0 01-1.99 1.79H4.802a2 2 0 01-1.99-1.79L2 7.5zM7 11a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" />
        </svg>
        ここにドロップしてアーカイブ
    </span>
</div>

{{-- 掴んで横へ動かせる場所を増やすため、画面の下まで広げる --}}
<div class="flex flex-1 items-start gap-4 overflow-x-auto pb-4"
     x-drag-scroll
     x-board="{
        tasksUrl: @js(route('tasks.reorder', $project)),
        listsUrl: @js(route('lists.reorder', $project)),
        archiveUrl: @js(route('tasks.archive-many', $project)),
     }">
    @foreach ($project->taskLists as $list)
        <section data-list-id="{{ $list->id }}" x-data="{ adding: false }"
                 class="flex w-72 shrink-0 flex-col rounded-xl bg-slate-200/70 shadow-sm">
            {{-- 上端の帯。見出しと同じくリストを掴める --}}
            <div data-list-handle class="h-2 cursor-grab rounded-t-xl" style="background-color: {{ $list->color }}"></div>

            {{-- 見出し --}}
            <div>
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
                        <button type="button" role="menuitem"
                                @click="menuOpen = false; $dispatch('open-move', { kind: 'list', name: @js($list->name), action: @js(route('lists.move', $list)) })"
                                class="block w-full px-4 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                            リストを移動
                        </button>
                        <form autocomplete="off" method="POST" action="{{ route('lists.duplicate', $list) }}">
                            @csrf
                            <x-board-menu-item type="submit">リストを複製</x-board-menu-item>
                        </form>

                        <div class="my-1.5 border-t border-slate-100"></div>

                        <form autocomplete="off" method="POST" action="{{ route('lists.archive', $list) }}"
                              onsubmit="return confirm(@js($list->name.' をアーカイブします。中のタスクも一緒にボードから消えます。よろしいですか？'));">
                            @csrf
                            <x-board-menu-item type="submit">リストをアーカイブ</x-board-menu-item>
                        </form>
                    </x-board-menu>
                </div>
            </div>

            {{-- タスクを追加 --}}
            <div class="px-2 pt-2">
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
            <div data-tasks="{{ $list->id }}" class="min-h-8 space-y-2 p-2">
                @foreach ($list->tasks as $task)
                    <article data-task-id="{{ $task->id }}"
                             x-data="{ editing: false }"
                             class="cursor-grab rounded-lg bg-white p-2.5 shadow-xs ring-1 ring-slate-200 transition hover:ring-slate-300">
                        <div x-show="! editing" class="flex items-start gap-2">
                            <span class="mt-0.5 shrink-0 text-[10px] font-medium text-slate-400">#{{ $task->number }}</span>
                            <p class="min-w-0 flex-1 text-sm text-slate-800">{{ $task->title }}</p>

                            <x-board-menu title="タスクメニュー" button-class="p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                <x-board-menu-item @click="menuOpen = false; editing = true">タスクを開く</x-board-menu-item>

                                <div class="my-1.5 border-t border-slate-100"></div>

                                <form autocomplete="off" method="POST" action="{{ route('tasks.duplicate', $task) }}">
                                    @csrf
                                    <x-board-menu-item type="submit">タスクを複製</x-board-menu-item>
                                </form>
                                <button type="button" role="menuitem"
                                        @click="menuOpen = false; $dispatch('open-move', { kind: 'task', name: @js($task->title), action: @js(route('tasks.move', $task)), listId: {{ $list->id }} })"
                                        class="block w-full px-4 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                                    タスクを移動
                                </button>

                                <div class="my-1.5 border-t border-slate-100"></div>

                                <form autocomplete="off" method="POST" action="{{ route('tasks.archive', $task) }}">
                                    @csrf
                                    <x-board-menu-item type="submit">タスクをアーカイブ</x-board-menu-item>
                                </form>
                            </x-board-menu>
                        </div>

                        <div x-show="editing" x-cloak class="space-y-2">
                            <form autocomplete="off" method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-2">
                                @csrf
                                @method('PUT')
                                <input autocomplete="off" type="text" name="title" value="{{ $task->title }}" required maxlength="255"
                                       class="block w-full rounded-lg border border-slate-300 px-2 py-1 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-semibold text-white hover:bg-sky-700">保存</button>
                                    <button type="button" @click="editing = false" class="text-xs text-slate-600 hover:text-slate-900">やめる</button>
                                </div>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

        </section>
    @endforeach

    {{-- リストを追加 --}}
    <div class="w-72 shrink-0" x-data="{ adding: false }">
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

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
     class="hidden fixed bottom-6 left-1/2 z-40 max-h-20 w-80 -translate-x-1/2 overflow-hidden rounded-xl border-2 border-dashed border-amber-400 bg-amber-50 px-6 py-5 text-center text-sm font-semibold text-amber-800 shadow-lg has-[[data-task-id]]:bg-amber-200">
    <span class="pointer-events-none">ここにドロップしてアーカイブ</span>
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
        <section data-list-id="{{ $list->id }}"
                 class="flex w-72 shrink-0 flex-col rounded-xl bg-slate-200/70 shadow-sm">
            {{-- 上端の帯。見出しと同じくリストを掴める --}}
            <div data-list-handle class="h-2 cursor-grab rounded-t-xl" style="background-color: {{ $list->color }}"></div>

            {{-- 見出し --}}
            <div x-data="{ editing: false }">
                <div data-list-handle class="flex cursor-grab items-center gap-1.5 px-3 pb-1 pt-2" x-show="! editing">
                    <span class="min-w-0 truncate text-sm text-slate-900">{{ $list->name }}</span>
                    <span data-list-count class="shrink-0 text-sm text-sky-600">{{ $list->tasks->count() }}</span>

                    <button type="button" @click="editing = true" aria-label="リストを編集"
                            class="ml-auto shrink-0 rounded p-1 text-slate-700 transition hover:bg-black/10">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM11.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM17 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                        </svg>
                    </button>
                </div>

                {{-- 名前と色の変更 --}}
                <form autocomplete="off" method="POST" action="{{ route('lists.update', $list) }}" x-show="editing" x-cloak
                      class="space-y-2 bg-white p-3 ring-1 ring-slate-200">
                    @csrf
                    @method('PUT')

                    <input autocomplete="off" type="text" name="name" value="{{ $list->name }}" required maxlength="255"
                           class="block w-full rounded-lg border border-slate-300 px-2 py-1 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">

                    <x-list-color-picker :value="$list->color" />

                    <div class="flex items-center gap-2">
                        <button type="submit" class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-semibold text-white hover:bg-sky-700">保存</button>
                        <button type="button" @click="editing = false" class="text-xs text-slate-600 hover:text-slate-900">やめる</button>
                    </div>
                </form>

                {{-- 誤って押さないよう、アーカイブは編集を開いたときだけ出す --}}
                <form autocomplete="off" method="POST" action="{{ route('lists.archive', $list) }}" x-show="editing" x-cloak
                      class="border-t border-slate-100 bg-white px-3 pb-3 pt-2"
                      onsubmit="return confirm(@js($list->name.' をアーカイブします。中のタスクも一緒にボードから消えます。よろしいですか？'));">
                    @csrf
                    <button type="submit" class="text-xs text-amber-700 hover:text-amber-900">リストをアーカイブ</button>
                </form>
            </div>

            {{-- タスクを追加 --}}
            <div class="px-2 pt-2" x-data="{ adding: false }">
                <button type="button" @click="adding = true; $nextTick(() => $refs.title.focus())" x-show="! adding"
                        class="flex w-full items-center justify-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm text-sky-600 transition hover:bg-sky-50">
                    ＋ タスクを追加
                </button>

                <form autocomplete="off" method="POST" action="{{ route('tasks.store', $list) }}" x-show="adding" x-cloak class="space-y-2">
                    @csrf
                    <input autocomplete="off" type="text" name="title" x-ref="title" required maxlength="255" placeholder="タスク名"
                           class="block w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
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

                            <button type="button" @click="editing = true" aria-label="タスクを編集"
                                    class="shrink-0 rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M6 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM11.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM17 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                </svg>
                            </button>
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

                            <form autocomplete="off" method="POST" action="{{ route('tasks.archive', $task) }}">
                                @csrf
                                <button type="submit" class="text-xs text-amber-700 hover:text-amber-900">アーカイブ</button>
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

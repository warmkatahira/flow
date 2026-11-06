@php
    use App\Enums\TaskStatus;

    $assigneeIds = $task->assignees->pluck('id');
    $rowClass = 'flex items-start gap-3 text-sm text-slate-500';
    $inputClass = 'block w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none';
    $saveClass = 'rounded-lg bg-sky-600 px-3 py-1 text-xs font-semibold text-white hover:bg-sky-700';
    $cancelClass = 'text-xs text-slate-600 hover:text-slate-900';
@endphp

{{-- 見出し --}}
<div class="border-b border-slate-200 px-6 pb-4 pt-5">
    <div class="flex items-start justify-between gap-4">
        <p class="min-w-0 truncate pt-1.5 text-xs text-slate-500">#{{ $task->number }} {{ $task->list->name }}</p>

        <div class="flex shrink-0 items-center gap-1">
            {{-- ステータス --}}
            <div x-data="{ statusOpen: false }" class="relative" @click.outside="statusOpen = false">
                <button type="button" @click="statusOpen = ! statusOpen" aria-haspopup="menu" :aria-expanded="statusOpen ? 'true' : 'false'"
                        class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm transition hover:bg-slate-50 {{ $task->status->textClass() }}">
                    <x-task-status-icon :status="$task->status" class="h-4 w-4" />
                    {{ $task->status->label() }}
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                    </svg>
                </button>

                <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.status', $task) }}"
                      x-show="statusOpen" x-cloak role="menu"
                      class="absolute right-0 z-20 mt-1 w-36 rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200">
                    @csrf
                    @method('PUT')
                    @foreach (TaskStatus::cases() as $status)
                        <button type="submit" name="status" value="{{ $status->value }}" role="menuitem"
                                class="flex w-full items-center gap-2.5 px-3 py-1.5 text-left text-sm transition hover:bg-slate-100 {{ $status->textClass() }}">
                            <x-task-status-icon :status="$status" class="h-5 w-5" />
                            {{ $status->label() }}
                        </button>
                    @endforeach
                </form>
            </div>

            {{-- このタスクを直接開くリンク --}}
            <button type="button" x-data="{ copied: false }" aria-label="リンクをコピー"
                    :title="copied ? 'コピーしました' : 'リンクをコピー'"
                    @click="navigator.clipboard?.writeText(@js(route('projects.show', [$task->project_id, 'task' => $task->id]))); copied = true; setTimeout(() => copied = false, 1500)"
                    :class="copied ? 'text-emerald-500' : 'text-slate-500'"
                    class="rounded-lg p-1.5 transition hover:bg-slate-100">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" />
                    <path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" />
                </svg>
            </button>

            <button type="button" @click="close()" aria-label="閉じる"
                    class="rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>
    </div>

    {{-- タイトル。フォーカスを外すと保存する（resources/js/app.js の blurEdit） --}}
    <div x-data="blurEdit(@js($task->title))" class="mt-1">
        <h2 x-show="! editing" @click="editing = true; $nextTick(() => $refs.title.select())"
            class="cursor-text break-words text-xl font-bold text-slate-800">{{ $task->title }}</h2>

        <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.update', $task) }}" x-show="editing" x-cloak>
            @csrf
            @method('PUT')
            <input autocomplete="off" type="text" name="title" x-ref="title" value="{{ $task->title }}" required maxlength="255" aria-label="タイトル"
                   @blur="finish($el)" @keydown.enter.prevent="$el.blur()" @keydown.escape.prevent.stop="cancel($el)"
                   class="{{ $inputClass }}">
        </form>
    </div>

    {{-- 説明 --}}
    <div x-data="blurEdit(@js($task->description ?? ''))" class="mt-2">
        <button type="button" x-show="! editing" @click="editing = true; $nextTick(() => $refs.description.focus())"
                class="block w-full whitespace-pre-wrap break-words text-left text-sm {{ $task->description ? 'text-slate-700' : 'text-slate-400 hover:text-slate-600' }}">{{ $task->description ?: '説明を編集' }}</button>

        <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.update', $task) }}" x-show="editing" x-cloak>
            @csrf
            @method('PUT')
            <textarea name="description" x-ref="description" rows="4" maxlength="10000" aria-label="説明"
                      @blur="finish($el)" @keydown.escape.prevent.stop="cancel($el)"
                      class="{{ $inputClass }}">{{ $task->description }}</textarea>
        </form>
    </div>
</div>

<div class="space-y-3 px-6 py-4">
    {{-- 期間 --}}
    <div x-data="periodPicker(@js($task->started_on?->format('Y-m-d')), @js($task->ended_on?->format('Y-m-d')))" class="{{ $rowClass }}">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" />
        </svg>

        <div class="min-w-0 flex-1">
            <button type="button" @click="$refs.calendar.showModal()" class="text-left hover:text-slate-800 {{ $task->started_on || $task->ended_on ? 'text-slate-700' : '' }}">
                @if ($task->started_on || $task->ended_on)
                    {{ $task->started_on?->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }} 〜 {{ $task->ended_on?->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }}
                @else
                    期間を設定
                @endif
            </button>

            {{-- 1回目に押した日が開始、2回目に押した日が終了（resources/js/app.js の periodPicker） --}}
            <dialog x-ref="calendar" @click.self="$refs.calendar.close()"
                    class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-xl rounded-2xl bg-white shadow-xl outline-none backdrop:bg-slate-900/20">
                <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.period', $task) }}" class="relative p-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="started_on" :value="start ?? ''">
                    <input type="hidden" name="ended_on" :value="end ?? ''">

                    <h3 class="text-center text-sm text-slate-700">期間の設定</h3>

                    <button type="button" @click="$refs.calendar.close()" aria-label="閉じる"
                            class="absolute right-3 top-3 rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>

                    <div class="mt-4 grid gap-6 sm:grid-cols-2" @mouseleave="hovered = null">
                        <template x-for="(month, index) in months" :key="month.label">
                            <div>
                                <div class="flex items-center justify-between">
                                    <button type="button" @click="shift(-1)" aria-label="前の月" :class="index === 0 ? '' : 'invisible'"
                                            class="rounded-md bg-sky-50 p-1 text-sky-500 transition hover:bg-sky-100">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M12.5 4.5v11L6 10z" />
                                        </svg>
                                    </button>
                                    <span class="text-sm text-slate-700" x-text="month.label"></span>
                                    <button type="button" @click="shift(1)" aria-label="次の月" :class="index === months.length - 1 ? '' : 'invisible'"
                                            class="rounded-md bg-sky-50 p-1 text-sky-500 transition hover:bg-sky-100">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M7.5 4.5v11L14 10z" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-2 grid grid-cols-7 text-center text-xs text-slate-400">
                                    @foreach (['日', '月', '火', '水', '木', '金', '土'] as $weekday)
                                        <span class="py-1">{{ $weekday }}</span>
                                    @endforeach
                                </div>

                                <div class="grid grid-cols-7 gap-y-0.5 text-center text-sm">
                                    <template x-for="blank in month.blanks" :key="`blank-${blank}`"><span></span></template>
                                    <template x-for="day in month.days" :key="day.date">
                                        <button type="button" @click="pick(day.date)" @mouseenter="hovered = day.date"
                                                :aria-pressed="[start, end].includes(day.date) ? 'true' : 'false'" :aria-label="day.date"
                                                :class="dayClass(day.date)" class="h-8 rounded-md transition-colors" x-text="day.number"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-5 flex justify-center gap-4">
                        <button type="button" @click="clear()"
                                class="w-36 rounded-lg bg-sky-50 py-2 text-sm text-sky-600 transition hover:bg-sky-100">
                            クリア
                        </button>
                        <button type="submit" :disabled="! dirty"
                                class="w-36 rounded-lg bg-sky-500 py-2 text-sm text-white transition hover:bg-sky-600 disabled:bg-slate-200 disabled:text-slate-400">
                            保存
                        </button>
                    </div>
                </form>
            </dialog>
        </div>
    </div>

    {{-- 担当者 --}}
    <div x-data="{ picking: false }" class="{{ $rowClass }} relative" @click.outside="picking = false">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
        </svg>

        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
            @foreach ($task->assignees as $assignee)
                <span class="inline-flex items-center gap-1.5 text-slate-700">
                    <x-user-avatar :user="$assignee" size="xxs" />
                    {{ $assignee->name }}
                </span>
            @endforeach

            <button type="button" @click="picking = ! picking" class="hover:text-slate-800">担当者を{{ $task->assignees->isEmpty() ? '追加' : '変更' }}</button>
        </div>

        <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.assignee', $task) }}"
              x-show="picking" x-cloak
              class="absolute left-8 top-7 z-20 max-h-56 w-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200">
            @csrf
            @foreach ($task->project->users as $user)
                <button type="submit" name="user_id" value="{{ $user->id }}"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                    <x-user-avatar :user="$user" size="xxs" />
                    <span class="min-w-0 flex-1 truncate">{{ $user->name }}</span>
                    @if ($assigneeIds->contains($user->id))
                        <svg class="h-4 w-4 shrink-0 text-sky-500" viewBox="0 0 20 20" fill="currentColor" aria-label="担当中">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
                        </svg>
                    @endif
                </button>
            @endforeach
        </form>
    </div>

    {{-- チェックリスト --}}
    <div x-data="{ adding: false }" class="{{ $rowClass }}">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M6 4.75A.75.75 0 016.75 4h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 4.75zM6 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 10zm0 5.25a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75a.75.75 0 01-.75-.75zM1.99 4.75a1 1 0 011-1H3a1 1 0 011 1v.01a1 1 0 01-1 1h-.01a1 1 0 01-1-1v-.01zM1.99 15.25a1 1 0 011-1H3a1 1 0 011 1v.01a1 1 0 01-1 1h-.01a1 1 0 01-1-1v-.01zM1.99 10a1 1 0 011-1H3a1 1 0 011 1v.01a1 1 0 01-1 1h-.01a1 1 0 01-1-1V10z" />
        </svg>

        <div class="min-w-0 flex-1">
            <button type="button" x-show="! adding" @click="adding = true; $nextTick(() => $refs.checklist.focus())" class="text-left hover:text-slate-800">
                チェックリストの新規作成
            </button>

            <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.checklists.store', $task) }}" x-show="adding" x-cloak class="space-y-2">
                @csrf
                <input autocomplete="off" type="text" name="title" x-ref="checklist" required maxlength="255" placeholder="チェックリスト名" class="{{ $inputClass }}">
                <div class="flex items-center gap-2">
                    <button type="submit" class="{{ $saveClass }}">作成</button>
                    <button type="button" @click="adding = false" class="{{ $cancelClass }}">やめる</button>
                </div>
            </form>
        </div>
    </div>

    {{-- チェックリスト。1つごとに枠を作り、中に項目を並べる。どちらも取っ手のドラッグで動かせる --}}
    <div class="space-y-3"
         x-sortable="{ url: @js(route('tasks.checklists.reorder', $task)), attribute: 'data-checklist-id', handle: '[data-checklist-handle]' }">
    @foreach ($task->checklists as $checklist)
        <section data-checklist-id="{{ $checklist->id }}" x-data="{ open: true, editing: false }" class="rounded-xl border border-slate-300 bg-white px-4 py-3">
            <div class="flex items-center gap-2">
                <span data-checklist-handle title="ドラッグで並べ替え" class="-ml-1.5 shrink-0 cursor-grab text-slate-300 hover:text-slate-500">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M7 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM5.5 17a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM14.5 11.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 15.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                </span>

                <h3 x-show="! editing" @click="editing = true; $nextTick(() => $refs.name.select())"
                    class="min-w-0 flex-1 cursor-text break-words text-sm font-bold text-slate-800">{{ $checklist->title }}</h3>

                <form data-task-form autocomplete="off" method="POST" action="{{ route('checklists.update', $checklist) }}" x-show="editing" x-cloak
                      class="flex min-w-0 flex-1 items-center gap-2">
                    @csrf
                    @method('PUT')
                    <input autocomplete="off" type="text" name="title" x-ref="name" value="{{ $checklist->title }}" required maxlength="255" aria-label="チェックリスト名" class="{{ $inputClass }}">
                    <button type="submit" class="{{ $saveClass }} shrink-0">保存</button>
                    <button type="button" @click="editing = false" class="{{ $cancelClass }} shrink-0">やめる</button>
                </form>

                <form data-task-form autocomplete="off" method="POST" action="{{ route('checklists.destroy', $checklist) }}"
                      onsubmit="return confirm(@js($checklist->title.' を削除します。中の項目も一緒に消えます。よろしいですか？'));">
                    @csrf
                    @method('DELETE')
                    <button type="submit" aria-label="{{ $checklist->title }} を削除" class="rounded p-1 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </form>

                <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-label="開閉"
                        class="rounded p-1 text-slate-400 transition hover:bg-slate-100">
                    <svg class="h-4 w-4 transition-transform duration-200" :class="open || 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" />
                    </svg>
                </button>
            </div>

            <div class="mt-2 flex items-center gap-3">
                <span class="w-9 shrink-0 text-xs text-sky-500">{{ $checklist->progress() }}%</span>
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sky-50">
                    <div class="h-full rounded-full bg-sky-500 transition-[width] duration-300" style="width: {{ $checklist->progress() }}%"></div>
                </div>
            </div>

            <div x-show="open" x-collapse class="text-sm">
                <ul class="mt-2 min-h-2" data-reorder-url="{{ route('checklists.items.reorder', $checklist) }}"
                    x-checklist-items="{{ $task->id }}">
                    @foreach ($checklist->items as $item)
                        <li data-item-id="{{ $item->id }}" class="flex items-center gap-2 bg-white py-1.5">
                            <span data-item-handle title="ドラッグで並べ替え" class="-ml-1.5 shrink-0 cursor-grab text-slate-300 hover:text-slate-500">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M7 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM5.5 17a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM14.5 11.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16 15.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                </svg>
                            </span>

                            <form data-task-form autocomplete="off" method="POST" action="{{ route('checklist-items.toggle', $item) }}" class="min-w-0 flex-1">
                                @csrf
                                <button type="submit" role="checkbox" aria-checked="{{ $item->is_done ? 'true' : 'false' }}"
                                        class="flex w-full items-center gap-3 text-left">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border {{ $item->is_done ? 'border-sky-500 bg-sky-500 text-white' : 'border-slate-400 bg-white' }}">
                                        @if ($item->is_done)
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
                                            </svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0 break-words {{ $item->is_done ? 'text-slate-400 line-through' : 'text-slate-700' }}">{{ $item->title }}</span>
                                </button>
                            </form>

                            <form data-task-form autocomplete="off" method="POST" action="{{ route('checklist-items.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="{{ $item->title }} を削除"
                                        class="rounded p-0.5 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                    </svg>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                {{-- Enter で続けて入力できるよう、送信後もここへカーソルを戻す --}}
                <form data-task-form data-refocus="#checklist-item-{{ $checklist->id }}" autocomplete="off" method="POST"
                      action="{{ route('checklists.items.store', $checklist) }}" class="flex items-center gap-3 py-1.5">
                    @csrf
                    <span class="ml-[18px] h-5 w-5 shrink-0 rounded-md border border-slate-200"></span>
                    <input autocomplete="off" id="checklist-item-{{ $checklist->id }}" type="text" name="title" required maxlength="255"
                           placeholder="アイテムを追加" aria-label="アイテムを追加"
                           class="block w-full bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none">
                </form>
            </div>
        </section>
    @endforeach
    </div>
</div>

{{-- コメント --}}
<div class="space-y-4 px-6 pb-5">
    {{-- @ を打つとプロジェクトの参加者から選べる（resources/js/app.js の mentionBox） --}}
    <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.comments.store', $task) }}"
          x-data="mentionBox(@js($task->project->users->map->only('id', 'name')->values()))"
          class="relative rounded-xl border border-slate-300 p-3 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/30">
        @csrf
        <textarea name="body" x-ref="body" rows="2" required maxlength="10000" placeholder="コメントを書く（@ でメンション）" aria-label="コメント"
                  @input="update()" @click="update()" @keydown="keydown($event)" @blur="candidates = []"
                  class="block w-full resize-none text-sm text-slate-800 focus:outline-none"></textarea>

        <ul x-show="candidates.length > 0" x-cloak role="listbox" aria-label="メンションする人"
            class="absolute left-3 top-full z-20 -mt-8 max-h-48 w-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200">
            <template x-for="(member, index) in candidates" :key="member.id">
                <li role="option" :aria-selected="index === active ? 'true' : 'false'"
                    @mousedown.prevent="choose(member)" @mouseenter="active = index"
                    :class="index === active && 'bg-sky-50'"
                    class="cursor-pointer truncate px-3 py-1.5 text-sm text-slate-700" x-text="member.name"></li>
            </template>
        </ul>

        <div class="mt-1 flex justify-end">
            <button type="submit" class="rounded-lg bg-sky-500 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-600">コメントを投稿</button>
        </div>
    </form>

    @foreach ($task->comments as $comment)
        <div class="flex gap-3">
            <x-user-avatar :user="$comment->user" />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="text-slate-700">{{ $comment->user->name }}</span>
                    <span>{{ $comment->created_at->format('Y年m月d日 H:i') }}</span>

                    @if ($comment->user_id === auth()->id())
                        <form data-task-form autocomplete="off" method="POST" action="{{ route('comments.destroy', $comment) }}" class="ml-auto"
                              onsubmit="return confirm('コメントを削除します。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-slate-400 hover:text-slate-700">削除</button>
                        </form>
                    @endif
                </div>

                <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800">{{ $comment->bodyHtml() }}</p>
            </div>
        </div>
    @endforeach
</div>

<div class="border-t border-slate-200 px-6 py-3 text-xs text-slate-400">
    タスク作成: {{ $task->created_at->format('Y年m月d日 H:i') }}
    @if ($task->creator)
        <span class="ml-3">作成者: {{ $task->creator->name }}</span>
    @endif
</div>

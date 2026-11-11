@php
    use App\Enums\TaskStatus;

    $assigneeIds = $task->assignees->pluck('id');
    $following = $task->followers()->whereKey(auth()->id())->exists();
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

            {{-- フォロー。フォローしたタスクに動きがあるとベルに通知が届く --}}
            <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.follow', $task) }}">
                @csrf
                <button type="submit" aria-pressed="{{ $following ? 'true' : 'false' }}"
                        title="{{ $following ? 'フォローをやめる' : 'フォローすると、このタスクに動きがあったときに通知が届きます' }}"
                        class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm transition hover:bg-slate-100 {{ $following ? 'text-sky-600' : 'text-slate-500' }}">
                    <x-follow-icon :solid="$following" class="h-5 w-5" />
                    {{ $following ? 'フォロー中' : 'フォロー' }}
                </button>
            </form>

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

    {{-- 説明。押すと書式付きのエディタになり、外へフォーカスが出ると保存する（resources/js/rich-text.js の richDescription） --}}
    <div x-data="richDescription(@js($task->description ?? ''))" @focusout="focusout($event)" class="mt-2">
        <div x-show="! editing" role="button" tabindex="0" aria-label="説明を編集"
             @click="start()" @keydown.enter.prevent="start()"
             class="block w-full cursor-text break-words rounded-md text-left text-sm {{ $task->description ? 'text-slate-700' : 'text-slate-400 hover:text-slate-600' }}">
            @if ($task->description)
                <div class="rich-text">{{ $task->descriptionHtml() }}</div>
            @else
                説明を編集
            @endif
        </div>

        <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.update', $task) }}" x-show="editing" x-cloak
              class="rounded-lg border border-slate-300 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/30">
            @csrf
            @method('PUT')
            <input type="hidden" name="description" x-ref="input">

            <div class="border-b border-slate-200 px-1.5 py-1">
                @include('projects.partials.rich-text-toolbar')
            </div>
            <div x-ref="editor" class="px-3 py-2 text-sm text-slate-700"></div>
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

            @include('projects.partials.period-dialog', ['action' => route('tasks.period', $task), 'heading' => '期間の設定'])
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

    {{-- タグ --}}
    @include('projects.partials.task-labels')

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

                            {{-- 項目の期間 --}}
                            <div x-data="periodPicker(@js($item->started_on?->format('Y-m-d')), @js($item->ended_on?->format('Y-m-d')))" class="shrink-0">
                                <button type="button" @click="$refs.calendar.showModal()"
                                        aria-label="{{ $item->title }} の期間{{ $item->periodLabel() ? '：'.$item->periodLabel() : 'を設定' }}"
                                        @class([
                                            'flex items-center gap-1 rounded px-1 py-0.5 text-xs transition hover:bg-slate-100',
                                            'text-slate-600' => $item->periodLabel(),
                                            'text-slate-300 hover:text-slate-600' => ! $item->periodLabel(),
                                        ])>
                                    <svg class="h-4 w-4 shrink-0 {{ $item->periodLabel() ? 'text-sky-500' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" />
                                    </svg>
                                    @if ($item->periodLabel())
                                        <span class="whitespace-nowrap">{{ $item->periodLabel() }}</span>
                                    @endif
                                </button>

                                @include('projects.partials.period-dialog', ['action' => route('checklist-items.period', $item), 'heading' => $item->title.' の期間'])
                            </div>

                            {{-- 項目の担当者（1人）。今の担当者をもう一度選ぶと外れる --}}
                            <div x-data="{ picking: false }" class="relative shrink-0" @click.outside="picking = false">
                                <button type="button" @click="picking = ! picking" aria-haspopup="menu" :aria-expanded="picking ? 'true' : 'false'"
                                        aria-label="{{ $item->title }} の担当者{{ $item->assignee ? '：'.$item->assignee->name : 'を設定' }}"
                                        title="{{ $item->assignee?->name ?? '担当者を設定' }}"
                                        class="flex items-center rounded p-0.5 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600">
                                    @if ($item->assignee)
                                        <x-user-avatar :user="$item->assignee" size="xxs" />
                                    @else
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                                        </svg>
                                    @endif
                                </button>

                                <form data-task-form autocomplete="off" method="POST" action="{{ route('checklist-items.assignee', $item) }}"
                                      x-show="picking" x-cloak role="menu"
                                      class="absolute right-0 top-full z-20 mt-1 max-h-56 w-56 overflow-y-auto rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200">
                                    @csrf
                                    @foreach ($task->project->users as $user)
                                        <button type="submit" name="user_id" value="{{ $user->id }}" role="menuitem"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100">
                                            <x-user-avatar :user="$user" size="xxs" />
                                            <span class="min-w-0 flex-1 truncate">{{ $user->name }}</span>
                                            @if ($item->assignee_id === $user->id)
                                                <svg class="h-4 w-4 shrink-0 text-sky-500" viewBox="0 0 20 20" fill="currentColor" aria-label="担当中">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
                                                </svg>
                                            @endif
                                        </button>
                                    @endforeach
                                    @if ($item->assignee)
                                        <button type="submit" name="user_id" value="" role="menuitem"
                                                class="mt-1 w-full border-t border-slate-100 px-3 pb-0.5 pt-2 text-left text-sm text-slate-500 transition hover:bg-slate-100">
                                            担当を外す
                                        </button>
                                    @endif
                                </form>
                            </div>

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
    {{--
        書式付きのエディタ。@ を打つとプロジェクトの参加者から選べる（resources/js/rich-text.js の commentEditor）
        ファイルはクリップのボタンで選ぶか、この枠にドラッグ＆ドロップして添付する
    --}}
    <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.comments.store', $task) }}" enctype="multipart/form-data"
          x-data="commentEditor(@js($task->project->users->map->only('id', 'name')->values()), @js(['files' => App\Models\TaskCommentAttachment::MAX_FILES, 'bytes' => App\Models\TaskCommentAttachment::MAX_KILOBYTES * 1024]))"
          @submit="submit($event)"
          @dragenter="dragenter($event)" @dragleave="dragging = Math.max(dragging - 1, 0)" @dragover.prevent @drop.prevent="drop($event)"
          :class="dragging > 0 ? 'border-sky-500 ring-2 ring-sky-500/30' : 'border-slate-300'"
          class="relative rounded-xl border focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/30">
        @csrf
        <input type="hidden" name="body" x-ref="input">
        <input type="file" name="attachments[]" x-ref="files" multiple class="hidden" tabindex="-1" aria-hidden="true">
        <input type="file" x-ref="picker" multiple class="hidden" @change="addFiles($event.target.files); $event.target.value = ''">

        {{-- ドラッグ中の受け皿 --}}
        <div x-show="dragging > 0" x-cloak
             class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center gap-2 rounded-xl bg-sky-50/90 text-sm font-semibold text-sky-600">
            <x-paperclip-icon class="h-5 w-5" />
            ここにドロップして添付
        </div>

        <div class="border-b border-slate-200 px-1.5 py-1">
            @include('projects.partials.rich-text-toolbar')
        </div>

        <div class="relative px-3 pt-2">
            <div x-ref="editor" class="text-sm text-slate-800"></div>

            <ul x-show="candidates.length > 0" x-cloak role="listbox" aria-label="メンションする人"
                class="absolute left-3 top-full z-20 mt-1 max-h-48 w-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200">
                <template x-for="(member, index) in candidates" :key="member.id">
                    <li role="option" :aria-selected="index === highlighted ? 'true' : 'false'"
                        @mousedown.prevent="choose(member)" @mouseenter="highlighted = index"
                        :class="index === highlighted && 'bg-sky-50'"
                        class="cursor-pointer truncate px-3 py-1.5 text-sm text-slate-700" x-text="member.name"></li>
                </template>
            </ul>
        </div>

        {{-- 添付するファイル --}}
        <ul x-show="files.length > 0" x-cloak class="flex flex-wrap gap-1.5 px-3 pt-2">
            <template x-for="(file, index) in files" :key="index">
                <li class="flex max-w-full items-center gap-1.5 rounded-lg bg-slate-100 py-1 pl-2 pr-1 text-xs text-slate-600">
                    <x-paperclip-icon class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                    <span class="min-w-0 truncate" x-text="file.name"></span>
                    <span class="shrink-0 text-slate-400" x-text="sizeLabel(file.size)"></span>
                    <button type="button" @click="removeFile(index)" :aria-label="`${file.name} を外す`"
                            class="shrink-0 rounded p-0.5 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </li>
            </template>
        </ul>
        <p x-show="fileError" x-cloak x-text="fileError" class="px-3 pt-2 text-xs text-rose-600"></p>

        <div class="flex items-center justify-between gap-3 px-3 pb-3 pt-1">
            <button type="button" @click="$refs.picker.click()" title="ファイルを添付（ドラッグ＆ドロップでも添付できます）"
                    class="flex items-center gap-1 rounded-lg px-1.5 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                <x-paperclip-icon class="h-4 w-4" />
                ファイルを添付
            </button>
            <button type="submit" class="rounded-lg bg-sky-500 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-600">コメントを投稿</button>
        </div>
    </form>

    @foreach ($task->comments as $comment)
        @php($mine = $comment->user_id === auth()->id())
        <div class="flex gap-3">
            <x-user-avatar :user="$comment->user" />

            {{-- 編集・削除は投稿した本人だけ --}}
            <div class="min-w-0 flex-1" x-data="{ editingComment: false }">
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="text-slate-700">{{ $comment->user->name }}</span>
                    <span>{{ $comment->created_at->locale('ja')->isoFormat('YYYY年MM月DD日(ddd) HH:mm') }}</span>
                    @if ($comment->isEdited())
                        <span>（編集済み {{ $comment->updated_at->locale('ja')->isoFormat('YYYY年MM月DD日(ddd) HH:mm') }}）</span>
                    @endif

                    @if ($mine)
                        <div x-show="! editingComment" class="ml-auto flex items-center gap-1.5">
                            <button type="button" @click="editingComment = true"
                                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white py-0.5 pl-1.5 pr-2.5 text-xs text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                </svg>
                                編集
                            </button>
                            <form data-task-form autocomplete="off" method="POST" action="{{ route('comments.destroy', $comment) }}"
                                  onsubmit="return confirm('コメントを削除します。よろしいですか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-white py-0.5 pl-1.5 pr-2.5 text-xs text-rose-600 transition hover:border-rose-500 hover:bg-rose-500 hover:text-white">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" />
                                    </svg>
                                    削除
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($mine)
                    {{-- 編集。押したときだけエディタを作る（resources/js/rich-text.js の commentEditor）。Escape でやめる --}}
                    <template x-if="editingComment">
                        <form data-task-form autocomplete="off" method="POST" action="{{ route('comments.update', $comment) }}"
                              x-data="commentEditor(@js($task->project->users->map->only('id', 'name')->values()), undefined, { content: @js($comment->body), hasAttachments: @js($comment->attachments->isNotEmpty()), cancel: () => editingComment = false })"
                              @submit="submit($event)"
                              class="relative mt-1.5 rounded-xl border border-slate-300 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/30">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="body" x-ref="input">

                            <div class="border-b border-slate-200 px-1.5 py-1">
                                @include('projects.partials.rich-text-toolbar')
                            </div>

                            <div class="relative px-3 pt-2">
                                <div x-ref="editor" class="text-sm text-slate-800"></div>

                                <ul x-show="candidates.length > 0" x-cloak role="listbox" aria-label="メンションする人"
                                    class="absolute left-3 top-full z-20 mt-1 max-h-48 w-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200">
                                    <template x-for="(member, index) in candidates" :key="member.id">
                                        <li role="option" :aria-selected="index === highlighted ? 'true' : 'false'"
                                            @mousedown.prevent="choose(member)" @mouseenter="highlighted = index"
                                            :class="index === highlighted && 'bg-sky-50'"
                                            class="cursor-pointer truncate px-3 py-1.5 text-sm text-slate-700" x-text="member.name"></li>
                                    </template>
                                </ul>
                            </div>

                            <div class="flex items-center justify-end gap-3 px-3 pb-3 pt-1">
                                <button type="button" @click="editingComment = false" class="{{ $cancelClass }}">やめる</button>
                                <button type="submit" class="{{ $saveClass }}">保存</button>
                            </div>
                        </form>
                    </template>
                @endif

                @if (trim($comment->bodyText()) !== '')
                    <div x-show="! editingComment" class="rich-text mt-1 break-words text-sm text-slate-800">{{ $comment->bodyHtml() }}</div>
                @endif

                {{-- 添付ファイル。画像は小さく出し、押すと別のタブで開く。それ以外はダウンロード --}}
                @if ($comment->attachments->isNotEmpty())
                    <div class="mt-1.5 flex flex-wrap items-end gap-2">
                        @foreach ($comment->attachments as $attachment)
                            @if ($attachment->isInlineImage())
                                <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener" title="{{ $attachment->name }}（{{ $attachment->humanSize() }}）"
                                   class="block overflow-hidden rounded-lg ring-1 ring-slate-200 transition hover:ring-sky-400">
                                    <img src="{{ route('attachments.show', $attachment) }}" alt="{{ $attachment->name }}" loading="lazy" class="h-24 max-w-48 object-cover">
                                </a>
                            @elseif ($attachment->isPdf())
                                {{-- PDF は押すとこの画面の上でプレビューする --}}
                                <x-pdf-preview :attachment="$attachment" class="max-w-full">
                                    <span class="flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">
                                        <span class="shrink-0 rounded bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">PDF</span>
                                        <span class="min-w-0 truncate">{{ $attachment->name }}</span>
                                        <span class="shrink-0 text-slate-400">{{ $attachment->humanSize() }}</span>
                                    </span>
                                </x-pdf-preview>
                            @else
                                <a href="{{ route('attachments.show', $attachment) }}" download="{{ $attachment->name }}"
                                   class="flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">
                                    <x-paperclip-icon class="h-4 w-4 shrink-0 text-slate-400" />
                                    <span class="min-w-0 truncate">{{ $attachment->name }}</span>
                                    <span class="shrink-0 text-slate-400">{{ $attachment->humanSize() }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- リアクション。押すたびに付け外しする --}}
                <form data-task-form autocomplete="off" method="POST" action="{{ route('comments.react', $comment) }}"
                      x-data="{ picking: false }" @click.outside="picking = false" class="relative mt-1.5 flex flex-wrap items-center gap-1.5">
                    @csrf
                    @foreach ($comment->reactionSummary(auth()->user()) as $reaction)
                        <button type="submit" name="emoji" value="{{ $reaction['emoji']->value }}"
                                title="{{ $reaction['users']->pluck('name')->join('、') }}"
                                aria-label="{{ $reaction['emoji']->label() }} {{ $reaction['users']->count() }}件{{ $reaction['mine'] ? '（自分も付けています）' : '' }}"
                                aria-pressed="{{ $reaction['mine'] ? 'true' : 'false' }}"
                                @class([
                                    'flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition',
                                    'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100' => $reaction['mine'],
                                    'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => ! $reaction['mine'],
                                ])>
                            <span class="text-sm leading-none">{{ $reaction['emoji']->value }}</span>
                            <span class="tabular-nums">{{ $reaction['users']->count() }}</span>
                        </button>
                    @endforeach

                    <button type="button" @click="picking = ! picking" aria-label="リアクションを付ける" aria-haspopup="menu" :aria-expanded="picking ? 'true' : 'false'"
                            class="flex h-6 items-center rounded-full border border-slate-200 px-1.5 text-slate-400 transition hover:bg-slate-50 hover:text-slate-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.536-4.464a.75.75 0 10-1.061-1.061 3.5 3.5 0 01-4.95 0 .75.75 0 00-1.06 1.06 5 5 0 007.07 0zM9 8.5c0 .828-.448 1.5-1 1.5s-1-.672-1-1.5S7.448 7 8 7s1 .672 1 1.5zm3 1.5c.552 0 1-.672 1-1.5S12.552 7 12 7s-1 .672-1 1.5.448 1.5 1 1.5z" />
                        </svg>
                        <span class="ml-0.5 text-xs leading-none">+</span>
                    </button>

                    <div x-show="picking" x-cloak role="menu"
                         class="absolute left-0 top-full z-20 mt-1 flex gap-0.5 rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-slate-200">
                        @foreach (\App\Enums\Reaction::cases() as $emoji)
                            <button type="submit" name="emoji" value="{{ $emoji->value }}" role="menuitem" title="{{ $emoji->label() }}" aria-label="{{ $emoji->label() }}"
                                    class="rounded-lg p-1 text-lg leading-none transition hover:bg-slate-100">{{ $emoji->value }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>

{{-- タグの文字数が違っても日時と名前の位置が揃うよう、表の形に並べる。最終更新は並べ替えとコメントでは変わらない --}}
<dl class="grid grid-cols-[max-content_max-content_1fr] gap-x-3 gap-y-0.5 border-t border-slate-200 px-6 py-3 text-xs text-slate-400">
    <dt>作成</dt>
    <dd>{{ $task->created_at->locale('ja')->isoFormat('YYYY年MM月DD日(ddd) HH:mm') }}</dd>
    <dd>{{ $task->creator?->name }}</dd>

    <dt>最終更新</dt>
    <dd>{{ $task->updated_at->locale('ja')->isoFormat('YYYY年MM月DD日(ddd) HH:mm') }}</dd>
    <dd>{{ $task->updater?->name }}</dd>
</dl>

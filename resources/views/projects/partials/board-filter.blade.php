@php
    use App\Enums\TaskStatus;

    $dueOptions = [
        'past' => '過去',
        'today' => '今日',
        'tomorrow' => '明日',
        'week' => '今週',
        'nextWeek' => '来週',
        'next14' => '今後の14日間',
        'custom' => 'カスタム',
    ];
    $checkClass = 'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition hover:bg-slate-100';
@endphp

{{-- ボードの絞り込み（resources/js/app.js の boardFilter）。画面の中で隠すだけで、データは変えない --}}
<div x-data="{ filterOpen: false }" x-show="tab === 'board'" x-init="$store.boardFilter.load({{ $project->id }}, {{ auth()->id() }})"
     class="relative flex shrink-0 items-center gap-2" @click.outside="filterOpen = false" @keydown.escape.window="filterOpen = false">
    <button type="button" @click="filterOpen = ! filterOpen" :aria-expanded="filterOpen ? 'true' : 'false'"
            :class="$store.boardFilter.count > 0 ? 'border-sky-500 bg-sky-50 text-sky-600' : 'border-slate-300 text-slate-500 hover:bg-slate-50'"
            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1 text-sm transition">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" />
        </svg>
        フィルター
        <span x-show="$store.boardFilter.count > 0" x-cloak x-text="$store.boardFilter.count"></span>
    </button>

    <button type="button" @click="$store.boardFilter.toggleMine()" :aria-pressed="$store.boardFilter.mine ? 'true' : 'false'"
            :class="$store.boardFilter.mine ? 'border-sky-500 bg-sky-50 text-sky-600' : 'border-slate-300 text-slate-500 hover:bg-slate-50'"
            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1 text-sm transition">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
        </svg>
        マイタスク
    </button>

    <div x-show="filterOpen" x-cloak
         class="absolute right-0 top-full z-30 mt-2 grid w-[36rem] max-w-[calc(100vw-2rem)] grid-cols-3 divide-x divide-slate-100 rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="p-3">
            <p class="mb-1 px-2 text-sm font-bold text-slate-600">ステータス</p>
            @foreach (TaskStatus::cases() as $status)
                <button type="button" @click="$store.boardFilter.toggle('statuses', @js($status->value))"
                        :class="$store.boardFilter.statuses.includes(@js($status->value)) && 'bg-sky-50'"
                        class="{{ $checkClass }} {{ $status->textClass() }}">
                    <x-task-status-icon :status="$status" class="h-5 w-5" />
                    {{ $status->label() }}
                </button>
            @endforeach
        </div>

        <div class="p-3">
            <p class="mb-1 px-2 text-sm font-bold text-slate-600">締切日</p>
            @foreach ($dueOptions as $value => $label)
                <button type="button" @click="$store.boardFilter.toggle('dues', @js($value))"
                        :class="$store.boardFilter.dues.includes(@js($value)) && 'bg-sky-50 text-sky-600'"
                        class="{{ $checkClass }} text-slate-700">
                    {{ $label }}
                </button>
            @endforeach

            <div x-show="$store.boardFilter.dues.includes('custom')" x-cloak class="mt-1 space-y-1 px-2">
                <input autocomplete="off" type="date" aria-label="締切日（から）" x-model="$store.boardFilter.from" @change="$store.boardFilter.changed()"
                       class="block w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-sky-500 focus:outline-none">
                <input autocomplete="off" type="date" aria-label="締切日（まで）" x-model="$store.boardFilter.to" @change="$store.boardFilter.changed()"
                       class="block w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-sky-500 focus:outline-none">
            </div>
        </div>

        <div class="flex min-h-0 flex-col p-3">
            <p class="mb-1 px-2 text-sm font-bold text-slate-600">担当者</p>
            <div class="max-h-60 overflow-y-auto [scrollbar-width:thin]">
                <button type="button" @click="$store.boardFilter.toggle('assignees', 0)"
                        :class="$store.boardFilter.assignees.includes(0) && 'bg-sky-50'"
                        class="{{ $checkClass }} text-slate-700">
                    <span class="h-5 w-5 shrink-0 rounded-full bg-slate-200"></span>
                    指定なし
                </button>
                @foreach ($project->users as $user)
                    <button type="button" @click="$store.boardFilter.toggle('assignees', {{ $user->id }})"
                            :class="$store.boardFilter.assignees.includes({{ $user->id }}) && 'bg-sky-50'"
                            class="{{ $checkClass }} text-slate-700">
                        <x-user-avatar :user="$user" size="xxs" />
                        <span class="min-w-0 truncate">{{ $user->name }}</span>
                    </button>
                @endforeach
            </div>

            <button type="button" @click="$store.boardFilter.clear()" class="mt-auto self-end px-2 pt-2 text-xs text-slate-500 hover:text-slate-800">
                クリア
            </button>
        </div>
    </div>
</div>

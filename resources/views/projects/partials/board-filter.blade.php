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

{{--
    ボードの絞り込み（resources/js/app.js の boardFilter）。画面の中で隠すだけで、データは変えない
    絞り込んでいるかはボタンの色と条件の数だけで知らせる（中身を並べるとタブにかぶるため）。
    マイタスク・フォロー中・未読ありはメニューから切り替え、ステータスなどの細かい条件は下の大きなパネルで選ぶ。
--}}
@php
    $quickFilters = [
        ['mine', 'toggleMine', 'マイタスク'],
        ['followed', 'toggleFollowed', 'フォロー中'],
        ['unread', 'toggleUnread', '未読あり'],
    ];
@endphp
<div x-data="{ filterOpen: false, menuOpen: false }" x-show="['board', 'calendar'].includes(tab)" x-init="$store.boardFilter.load({{ $project->id }}, {{ auth()->id() }})"
     class="relative flex shrink-0 items-center gap-1.5" @click.outside="filterOpen = false; menuOpen = false" @keydown.escape.window="filterOpen = false; menuOpen = false">
    {{-- 絞り込んでいるかだけが分かればよいので、中身は出さず、ボタンの色と使っている条件の数で知らせる --}}
    <button type="button" @click="menuOpen = ! menuOpen; filterOpen = false" :aria-expanded="menuOpen ? 'true' : 'false'"
            :class="$store.boardFilter.activeCount > 0
                ? 'border-sky-500 bg-sky-50 text-sky-700 hover:bg-sky-100'
                : 'border-dashed border-slate-300 text-slate-500 hover:border-slate-400 hover:text-slate-800'"
            class="inline-flex shrink-0 items-center gap-1 rounded-lg border px-2.5 py-1 text-sm transition">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" />
        </svg>
        <span x-text="$store.boardFilter.activeCount > 0 ? '絞り込み中' : '絞り込み'">絞り込み</span>
        <span x-show="$store.boardFilter.activeCount > 0" x-cloak x-text="$store.boardFilter.activeCount"
              class="ml-0.5 rounded-full bg-sky-600 px-1.5 text-[11px] leading-4 text-white"></span>
    </button>

    <div x-show="menuOpen" x-cloak x-transition role="menu"
         class="absolute right-0 top-full z-30 mt-2 w-64 rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-slate-200">
        <p class="px-2 pb-1 pt-1 text-xs font-bold text-slate-400">よく使う</p>
        @foreach ($quickFilters as [$field, $toggle, $label])
            <button type="button" role="menuitemcheckbox" @click="$store.boardFilter.{{ $toggle }}()"
                    :aria-checked="$store.boardFilter.{{ $field }} ? 'true' : 'false'" title="{{ $label }}"
                    class="{{ $checkClass }} text-slate-700">
                <span :class="$store.boardFilter.{{ $field }} ? 'border-sky-600 bg-sky-600' : 'border-slate-300'"
                      class="grid h-4 w-4 shrink-0 place-items-center rounded border text-white transition">
                    <svg x-show="$store.boardFilter.{{ $field }}" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
                    </svg>
                </span>
                {{ $label }}
                @if ($field === 'unread')
                    <span class="h-2 w-2 rounded-full bg-sky-500" aria-hidden="true"></span>
                @endif
            </button>
        @endforeach

        <hr class="my-1.5 border-slate-100">

        <button type="button" role="menuitem" @click="menuOpen = false; filterOpen = true" class="{{ $checkClass }} text-slate-700">
            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" />
            </svg>
            <span class="flex-1">ステータス・締切日・タグ・担当者…</span>
            <span x-show="$store.boardFilter.count > 0" x-cloak x-text="$store.boardFilter.count" class="rounded-full bg-sky-100 px-1.5 text-[11px] leading-4 text-sky-700"></span>
        </button>

        <button type="button" role="menuitem" x-show="$store.boardFilter.activeCount > 0" x-cloak @click="$store.boardFilter.clear(); menuOpen = false"
                class="{{ $checkClass }} text-rose-600 hover:bg-rose-50">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
            </svg>
            すべての絞り込みを外す
        </button>
    </div>

    <button type="button" x-show="tab === 'board'" @click="$store.cardView.toggle()" :aria-pressed="$store.cardView.expanded ? 'true' : 'false'"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1 text-sm text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
        <svg class="h-4 w-4 transition-transform duration-200" :class="$store.cardView.expanded && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
        </svg>
        <span x-text="$store.cardView.expanded ? '折り畳む' : '拡張する'">拡張する</span>
    </button>

    <div x-show="filterOpen" x-cloak
         class="absolute right-0 top-full z-30 mt-2 grid w-[46rem] max-w-[calc(100vw-2rem)] grid-cols-4 divide-x divide-slate-100 rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
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

        <div class="p-3">
            <p class="mb-1 px-2 text-sm font-bold text-slate-600">タグ</p>
            {{-- タグは増減するので、ボードと一緒に取り直す --}}
            <div data-live-filter-labels class="max-h-60 overflow-y-auto [scrollbar-width:thin]">
                @include('projects.partials.filter-labels')
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

            {{-- 絞り込んでいないときは押せない --}}
            <button type="button" @click="$store.boardFilter.clear()"
                    :disabled="$store.boardFilter.count === 0 && ! $store.boardFilter.mine && ! $store.boardFilter.followed && ! $store.boardFilter.unread"
                    class="group mt-auto inline-flex items-center gap-1.5 self-end rounded-full bg-rose-50 py-1 pl-1.5 pr-3 text-xs font-semibold text-rose-600 ring-1 ring-rose-200 transition hover:bg-rose-500 hover:text-white hover:ring-rose-500 disabled:cursor-default disabled:bg-slate-50 disabled:text-slate-300 disabled:ring-slate-200">
                <svg class="h-4 w-4 transition-transform duration-300 group-enabled:group-hover:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" />
                </svg>
                条件をクリア
            </button>
        </div>
    </div>
</div>

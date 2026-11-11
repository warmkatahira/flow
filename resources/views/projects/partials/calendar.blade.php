{{--
    期間のあるタスクをカレンダーに帯で出す（resources/js/app.js の taskCalendar）。
    表示は2つから選べる。
    - カレンダー: 月で区切らずに週が縦に続き、スクロールで前後の月へ進む。見出しはいちばん上に見えている月
    - タイムライン: 日付を横に並べ、1タスク＝1行でリストごとにまとめる。見出しは左端に見えている月
    押すとタスクの詳細が開く。帯の端のドラッグで開始日・終了日を、帯そのもののドラッグで期間を保ったまま日をずらせる。
    ボードの絞り込みもそのまま効く。中身の JSON は他の人の変更で差し替わる（x-project-live）。
--}}
@php
    $scrollbar = '[scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]';
@endphp
<div x-data="taskCalendar({{ $project->id }})" @calendar-refresh.window="load()" class="flex min-h-0 flex-1 flex-col pb-6 pr-4 sm:pr-6 lg:pr-8">
    <script type="application/json" x-ref="data" data-live-calendar>@json($calendarTasks)</script>

    <div class="flex shrink-0 flex-wrap items-center gap-3 pb-3">
        <h2 class="min-w-32 text-lg font-bold text-slate-800" x-text="label" aria-live="polite"></h2>

        <div class="flex items-center gap-1">
            <button type="button" @click="shift(-1)" aria-label="前の月"
                    class="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M12.5 4.5v11L6 10z" /></svg>
            </button>
            <button type="button" @click="goToday()"
                    class="rounded-md px-2.5 py-1 text-sm text-slate-600 ring-1 ring-slate-300 transition hover:bg-white">
                今日
            </button>
            <button type="button" @click="shift(1)" aria-label="次の月"
                    class="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7.5 4.5v11L14 10z" /></svg>
            </button>
        </div>

        {{-- 表示の切り替え --}}
        <div class="inline-flex rounded-lg bg-slate-200/70 p-0.5 text-sm" role="group" aria-label="表示">
            @foreach (['month' => 'カレンダー', 'timeline' => 'タイムライン'] as $value => $text)
                <button type="button" @click="view = @js($value)" :aria-pressed="view === @js($value) ? 'true' : 'false'"
                        :class="view === @js($value) ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                        class="rounded-md px-3 py-1 font-medium transition">{{ $text }}</button>
            @endforeach
        </div>

        <p x-show="! resizing" class="ml-auto text-xs text-slate-400">開始日か終了日を入れたタスクだけを出します。帯の端で期間を、帯そのもので日をずらせます</p>
        <p x-show="resizing" x-cloak class="ml-auto text-xs font-semibold text-sky-600"
           x-text="view === 'timeline' ? '左右の端に寄せると、スクロールして前後の月へ動かせます' : '上下の端に寄せると、スクロールして前後の月へ動かせます'"></p>
    </div>

    {{-- カレンダー（週が縦に続く） --}}
    <div x-show="view === 'month'" x-ref="scroller" @scroll.passive="view === 'month' && scrolled()"
         class="relative min-h-0 flex-1 overflow-y-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200 {{ $scrollbar }}">
        <div class="sticky top-0 z-10 grid grid-cols-7 border-b border-slate-200 bg-white text-center text-xs font-medium">
            @foreach (['日', '月', '火', '水', '木', '金', '土'] as $index => $weekday)
                <span @class(['py-2', 'text-rose-500' => $index === 0, 'text-sky-600' => $index === 6, 'text-slate-500' => $index > 0 && $index < 6])>{{ $weekday }}</span>
            @endforeach
        </div>

        <template x-if="view === 'month'">
            <div>
                <template x-for="week in weeks" :key="week.start">
                    <div :data-week="week.start" :data-week-end="week.end" :data-week-middle="week.middle"
                         class="relative border-b border-slate-100 last:border-b-0" :style="{ minHeight: `${week.height}px` }">
                        {{-- 日付のマス --}}
                        <div class="absolute inset-0 grid grid-cols-7">
                            <template x-for="day in week.days" :key="day.date">
                                <div :data-calendar-date="day.date" :class="day.shaded ? 'bg-slate-50' : ''" class="border-l border-slate-100 px-1.5 pt-1 first:border-l-0">
                                    <span :class="dayClass(day)" class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs" x-text="dayLabel(day)"></span>
                                </div>
                            </template>
                        </div>

                        {{-- タスクの帯 --}}
                        <template x-for="bar in week.bars" :key="bar.id">
                            <button type="button" @click="open(bar.task)" @pointerdown="startDrag($event, bar.task, 'move')" :disabled="! bar.task.url"
                                    :title="`#${bar.task.number} ${bar.task.title}（${bar.task.list}）`"
                                    :style="barStyle(bar)"
                                    :class="[
                                        bar.task.closed ? 'opacity-50 line-through' : '',
                                        bar.continuesBefore ? 'rounded-l-none border-l-0' : 'rounded-l-md',
                                        bar.continuesAfter ? 'rounded-r-none' : 'rounded-r-md',
                                        bar.task.periodUrl ? 'cursor-grab hover:brightness-95' : (bar.task.url ? 'cursor-pointer hover:brightness-95' : 'cursor-default'),
                                    ]"
                                    class="group/bar absolute flex h-5 items-center gap-1 overflow-hidden border-l-[3px] px-1.5 text-left text-xs leading-5 text-slate-800 transition">
                                @include('projects.partials.calendar-bar-body', ['task' => 'bar.task'])

                                {{-- 端を掴むと期間を変えられる。週をまたいで続く側の端には出さない --}}
                                <template x-if="bar.task.periodUrl && ! bar.continuesBefore">
                                    <span @pointerdown="startDrag($event, bar.task, 'start')" title="ドラッグで開始日を変更"
                                          class="absolute inset-y-0 left-0 w-2 cursor-ew-resize group-hover/bar:bg-slate-900/15"></span>
                                </template>
                                <template x-if="bar.task.periodUrl && ! bar.continuesAfter">
                                    <span @pointerdown="startDrag($event, bar.task, 'end')" title="ドラッグで終了日を変更"
                                          class="absolute inset-y-0 right-0 w-2 cursor-ew-resize group-hover/bar:bg-slate-900/15"></span>
                                </template>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- タイムライン（日付が横に並ぶ） --}}
    <div x-show="view === 'timeline'" x-cloak x-ref="timeline" @scroll.passive="view === 'timeline' && scrolled()"
         class="relative min-h-0 flex-1 overflow-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200 {{ $scrollbar }}">
        <template x-if="view === 'timeline'">
            <div class="relative flex min-h-full flex-col" :style="{ width: `${nameWidth + days.length * dayWidth}px` }">
                {{-- 見出し（上の行に月、下の行に日付と曜日）。上に残し、左のタスク名の見出しはさらに左にも残す --}}
                <div class="sticky top-0 z-20 flex h-[57px] border-b border-slate-200 bg-white">
                    <div class="sticky left-0 z-30 flex shrink-0 items-end border-r border-slate-200 bg-white px-3 pb-2 text-xs font-medium text-slate-500"
                         :style="{ width: `${nameWidth}px` }">タスク</div>

                    <div class="flex flex-col">
                        {{-- 月の行。月の名前は、その月が見えている間は左端に残す --}}
                        <div class="flex h-6 border-b border-slate-100">
                            <template x-for="month in months" :key="month.key">
                                <div class="shrink-0 border-l border-slate-200" :style="{ width: `${month.days * dayWidth}px` }">
                                    <span class="sticky inline-block whitespace-nowrap px-2 text-xs font-semibold leading-6 text-slate-600"
                                          :style="{ left: `${nameWidth}px` }" x-text="month.label"></span>
                                </div>
                            </template>
                        </div>

                        <div class="flex h-[33px]">
                            <template x-for="day in days" :key="day.date">
                                <div :class="[day.shaded ? 'bg-slate-50' : 'bg-white', day.number === 1 ? 'border-slate-200' : 'border-slate-100']"
                                     class="flex shrink-0 flex-col items-center justify-center border-l leading-none"
                                     :style="{ width: `${dayWidth}px` }">
                                    <span :class="dayClass(day)" class="rounded-full px-1 text-[11px] leading-4" x-text="day.number"></span>
                                    <span :class="day.weekday === 0 ? 'text-rose-400' : (day.weekday === 6 ? 'text-sky-500' : 'text-slate-400')" class="text-[10px]" x-text="weekdayLabel(day)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- 日付の列（帯を動かした先の日を調べるのにも使う）と今日の線 --}}
                <div class="absolute bottom-0 top-[57px] flex" :style="{ left: `${nameWidth}px` }">
                    <template x-for="day in days" :key="day.date">
                        <div :data-calendar-date="day.date"
                             :class="[day.weekday === 0 || day.weekday === 6 ? 'bg-slate-100/70' : (day.shaded ? 'bg-slate-50' : ''), day.number === 1 ? 'border-slate-200' : 'border-slate-100']"
                             class="h-full shrink-0 border-l" :style="{ width: `${dayWidth}px` }"></div>
                    </template>
                </div>
                <div class="pointer-events-none absolute bottom-0 top-[57px] z-[5] w-0.5 bg-sky-400/70" :style="{ left: `${todayLeft + dayWidth / 2}px` }"></div>

                {{-- 行（リストの見出しとタスク） --}}
                <template x-for="row in rows" :key="row.key">
                    <div class="relative flex h-8 items-center border-b border-slate-100">
                        <template x-if="row.list">
                            {{-- 行の区切り線の上も覆う（今日の線が区切り線のところで透けないように） --}}
                            <div class="sticky left-0 z-10 flex h-full shrink-0 items-center gap-2 border-r border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-600 shadow-[0_1px_0_var(--color-slate-100)]"
                                 :style="{ width: `${nameWidth}px` }">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: row.list.color }"></span>
                                <span class="truncate" x-text="row.list.name"></span>
                            </div>
                        </template>

                        <template x-if="row.task">
                            <button type="button" @click="open(row.task)" :disabled="! row.task.url"
                                    :title="`#${row.task.number} ${row.task.title}`"
                                    class="sticky left-0 z-10 flex h-full shrink-0 items-center gap-1.5 border-r border-slate-200 bg-white px-3 text-left text-xs text-slate-700 shadow-[0_1px_0_var(--color-slate-100)] enabled:hover:text-sky-700"
                                    :style="{ width: `${nameWidth}px` }">
                                <span x-show="$store.unread.hasTask(row.task.id)" data-unread-dot title="未読の通知あり"
                                      class="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"></span>
                                <span class="shrink-0 text-[10px] text-slate-400" x-text="`#${row.task.number}`"></span>
                                <span class="truncate" :class="row.task.closed && 'text-slate-400 line-through'" x-text="row.task.title"></span>
                            </button>
                        </template>

                        <template x-if="row.task">
                            <button type="button" @click="open(row.task)" @pointerdown="startDrag($event, row.task, 'move')" :disabled="! row.task.url"
                                    :title="`#${row.task.number} ${row.task.title}（${row.task.list}）`"
                                    :style="timelineBarStyle(row.task)"
                                    :class="[
                                        row.task.closed ? 'opacity-50 line-through' : '',
                                        row.task.periodUrl ? 'cursor-grab hover:brightness-95' : (row.task.url ? 'cursor-pointer hover:brightness-95' : 'cursor-default'),
                                    ]"
                                    class="group/bar absolute top-1.5 z-[6] flex h-5 items-center gap-1 overflow-hidden rounded-md border-l-[3px] px-1.5 text-left text-xs leading-5 text-slate-800 transition">
                                @include('projects.partials.calendar-bar-body', ['task' => 'row.task'])

                                <span x-show="row.task.periodUrl" @pointerdown="startDrag($event, row.task, 'start')" title="ドラッグで開始日を変更"
                                      class="absolute inset-y-0 left-0 w-2 cursor-ew-resize group-hover/bar:bg-slate-900/15"></span>
                                <span x-show="row.task.periodUrl" @pointerdown="startDrag($event, row.task, 'end')" title="ドラッグで終了日を変更"
                                      class="absolute inset-y-0 right-0 w-2 cursor-ew-resize group-hover/bar:bg-slate-900/15"></span>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- 行の下の余白も、左のタスク名の列は白く埋める（横にずれた日付の列が透けないように） --}}
                <div class="sticky left-0 z-10 flex-1 border-r border-slate-200 bg-white" :style="{ width: `${nameWidth}px` }">
                    <p x-show="rows.length === 0" class="px-3 py-10 text-xs text-slate-400">期間のあるタスクはありません。</p>
                </div>
            </div>
        </template>
    </div>
</div>

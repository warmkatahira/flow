{{--
    期間のあるタスクを月のカレンダーに帯で出す（resources/js/app.js の taskCalendar）。
    押すとタスクの詳細が開き、帯の端をドラッグすると開始日・終了日を変えられる。ボードの絞り込みもそのまま効く。
    中身の JSON は他の人の変更で差し替わる（x-project-live）。
--}}
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

        <p class="ml-auto text-xs text-slate-400">開始日か終了日を入れたタスクだけを出します。帯の端をドラッグすると期間を変えられます</p>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200 [scrollbar-color:var(--color-slate-300)_transparent] [scrollbar-width:thin]">
        <div class="sticky top-0 z-10 grid grid-cols-7 border-b border-slate-200 bg-white text-center text-xs font-medium">
            @foreach (['日', '月', '火', '水', '木', '金', '土'] as $index => $weekday)
                <span @class(['py-2', 'text-rose-500' => $index === 0, 'text-sky-600' => $index === 6, 'text-slate-500' => $index > 0 && $index < 6])>{{ $weekday }}</span>
            @endforeach
        </div>

        <template x-for="week in weeks" :key="week.start">
            <div class="relative border-b border-slate-100 last:border-b-0" :style="{ minHeight: `${week.height}px` }">
                {{-- 日付のマス --}}
                <div class="absolute inset-0 grid grid-cols-7">
                    <template x-for="day in week.days" :key="day.date">
                        <div :data-calendar-date="day.date" :class="day.inMonth ? '' : 'bg-slate-50'" class="border-l border-slate-100 px-1.5 pt-1 first:border-l-0">
                            <span :class="dayClass(day)" class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs" x-text="day.number"></span>
                        </div>
                    </template>
                </div>

                {{-- タスクの帯 --}}
                <template x-for="bar in week.bars" :key="bar.id">
                    <button type="button" @click="open(bar.task)" :disabled="! bar.task.url"
                            :title="`#${bar.task.number} ${bar.task.title}（${bar.task.list}）`"
                            :style="barStyle(bar)"
                            :class="[
                                bar.task.closed ? 'opacity-50 line-through' : '',
                                bar.continuesBefore ? 'rounded-l-none border-l-0' : 'rounded-l-md',
                                bar.continuesAfter ? 'rounded-r-none' : 'rounded-r-md',
                                bar.task.url ? 'cursor-pointer hover:brightness-95' : 'cursor-default',
                            ]"
                            class="group/bar absolute flex h-5 items-center gap-1 overflow-hidden border-l-[3px] px-1.5 text-left text-xs leading-5 text-slate-800 transition">
                        <span class="shrink-0 text-[10px] text-slate-500" x-text="`#${bar.task.number}`"></span>
                        <span class="truncate" x-text="bar.task.title"></span>

                        {{-- 端を掴むと期間を変えられる。週をまたいで続く側の端には出さない --}}
                        <template x-if="bar.task.periodUrl && ! bar.continuesBefore">
                            <span @pointerdown="startResize($event, bar.task, 'start')" title="ドラッグで開始日を変更"
                                  class="absolute left-0 inset-y-0 w-2 cursor-ew-resize group-hover/bar:bg-slate-900/15"></span>
                        </template>
                        <template x-if="bar.task.periodUrl && ! bar.continuesAfter">
                            <span @pointerdown="startResize($event, bar.task, 'end')" title="ドラッグで終了日を変更"
                                  class="absolute right-0 inset-y-0 w-2 cursor-ew-resize group-hover/bar:bg-slate-900/15"></span>
                        </template>
                    </button>
                </template>
            </div>
        </template>
    </div>
</div>

{{-- 期間を選ぶカレンダー。x-data="periodPicker(...)" の中に置き、$refs.calendar.showModal() で開く --}}
<dialog x-ref="calendar" @click.self="$refs.calendar.close()"
        class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-xl rounded-2xl bg-white shadow-xl outline-none backdrop:bg-slate-900/20">
    <form data-task-form autocomplete="off" method="POST" action="{{ $action }}" class="relative p-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="started_on" :value="start ?? ''">
        <input type="hidden" name="ended_on" :value="end ?? ''">

        <h3 class="text-center text-sm text-slate-700">{{ $heading }}</h3>

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

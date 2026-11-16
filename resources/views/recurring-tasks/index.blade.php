{{-- 定期タスクの一覧。ナビとタブは常に見えるよう、タブの下だけをスクロールさせる（設定画面と同じ） --}}
<x-layouts.app :title="$project->name.' の定期タスク'" fill sidebar>
    <x-project-header :project="$project" active="recurring" :archive-count="$archiveCount" />

    <div class="min-h-0 flex-1 overflow-y-auto">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-lg text-slate-800">定期タスク</h1>
                    <p class="mt-1 text-sm text-slate-500">決まった日の朝 6:00 に、ひな形からタスクを作ってボードに入れます。</p>
                </div>
                <a href="{{ route('recurring-tasks.create', $project) }}"
                   class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                    ＋ 定期タスクを作る
                </a>
            </div>

            @if ($recurringTasks->isEmpty())
                <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-10 text-center text-sm text-slate-500">
                    <p>定期タスクはまだありません。</p>
                    <p class="mt-1">「＋ 定期タスクを作る」か、タスクの詳細の「定期タスクにする」から作れます。</p>
                </div>
            @else
                <ul class="mt-6 space-y-3">
                    @foreach ($recurringTasks as $recurring)
                        @php
                            $muted = $recurring->isPaused() || $recurring->isFinished();
                        @endphp
                        <li @class(['flex flex-wrap items-center gap-x-6 gap-y-3 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200', 'opacity-70' => $muted])>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('recurring-tasks.edit', $recurring) }}" class="break-words text-slate-800 hover:text-sky-700">{{ $recurring->title }}</a>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span class="text-slate-700">{{ $recurring->summary() }}</span>
                                    @if ($recurring->list && ! $recurring->list->isArchived())
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $recurring->list->color }}"></span>
                                            {{ $recurring->list->name }}
                                        </span>
                                    @else
                                        <span>先頭のリスト</span>
                                    @endif
                                    @if ($recurring->ends_on)
                                        <span>{{ $recurring->ends_on->locale('ja')->isoFormat('YYYY/M/D(ddd)') }} まで</span>
                                    @endif
                                    <span>これまでに {{ $recurring->tasks_count }} 件</span>
                                </div>
                            </div>

                            {{-- 次に作る日 --}}
                            <div class="w-36 shrink-0 text-sm">
                                @if ($recurring->isPaused())
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs text-amber-700">一時停止中</span>
                                @elseif ($recurring->isFinished())
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">終了</span>
                                @else
                                    <span class="block text-xs text-slate-500">次に作る日</span>
                                    <span class="text-slate-800">{{ $recurring->next_run_on->locale('ja')->isoFormat('YYYY/M/D(ddd)') }}</span>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('recurring-tasks.edit', $recurring) }}"
                                   class="rounded-lg px-3 py-1.5 text-sm text-sky-700 transition hover:bg-sky-50">編集</a>
                                @unless ($recurring->isFinished())
                                    <form autocomplete="off" method="POST" action="{{ route('recurring-tasks.pause', $recurring) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-100">
                                            {{ $recurring->isPaused() ? '再開' : '一時停止' }}
                                        </button>
                                    </form>
                                @endunless
                                <form autocomplete="off" method="POST" action="{{ route('recurring-tasks.destroy', $recurring) }}"
                                      onsubmit="return confirm(@js($recurring->title.' を削除します。これまでに作ったタスクはボードに残ります。よろしいですか？'));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg px-3 py-1.5 text-sm text-rose-600 transition hover:bg-rose-50">削除</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layouts.app>

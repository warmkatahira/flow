{{--
    タスク一覧（参加しているプロジェクト全体で、自分が担当者になっているタスク）
    終了日の近い順。行を押すとプロジェクトのボードでそのタスクの詳細が開く
--}}
<x-layouts.app title="タスク一覧">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">タスク一覧</h1>
    </x-slot:header>

    <div x-data="{ query: '' }" class="px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            {{-- 完了・中止を出すかどうか --}}
            <div class="inline-flex rounded-lg bg-slate-200/70 p-0.5 text-sm">
                @foreach ([['label' => '未完了', 'count' => $openCount, 'all' => false], ['label' => 'すべて', 'count' => $allCount, 'all' => true]] as $tab)
                    <a href="{{ route('my-tasks.index', $tab['all'] ? ['show' => 'all'] : []) }}"
                       @if ($showAll === $tab['all']) aria-current="page" @endif
                       class="rounded-md px-3 py-1 font-medium transition {{ $showAll === $tab['all'] ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                        {{ $tab['label'] }}
                        <span class="ml-0.5 text-xs tabular-nums text-slate-400">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </div>

            @if ($tasks->isNotEmpty())
                <div class="relative w-full sm:w-72">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" />
                    </svg>
                    <input autocomplete="off" type="search" id="my-task-query" x-model="query" placeholder="タスク名・プロジェクト名で絞り込み"
                           class="block w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                </div>
            @endif
        </div>

        @if ($tasks->isEmpty())
            <div class="rounded-xl bg-white px-6 py-12 text-center shadow-sm">
                <p class="text-sm text-slate-500">
                    {{ $showAll ? '担当しているタスクはありません。' : '担当している未完了のタスクはありません。' }}
                </p>
                <p class="mt-1 text-xs text-slate-400">タスクの詳細で担当者に自分を入れると、ここに並びます。</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="w-full table-fixed text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-left text-xs text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">タスク</th>
                            <th class="hidden w-1/4 px-4 py-2.5 font-semibold md:table-cell">プロジェクト</th>
                            <th class="hidden w-36 px-4 py-2.5 font-semibold lg:table-cell">リスト</th>
                            <th class="w-24 px-4 py-2.5 font-semibold">ステータス</th>
                            <th class="hidden w-36 px-4 py-2.5 font-semibold sm:table-cell">期間</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tasks as $task)
                            @php
                                $url = route('projects.show', [$task->project, 'task' => $task->getKey()]);
                                $haystack = mb_strtolower($task->title.' '.$task->project->name);
                            @endphp
                            {{-- 行のどこを押しても開くよう、タスク名のリンクを行いっぱいに広げる --}}
                            <tr x-show="@js($haystack).includes(query.trim().toLowerCase())" data-my-task="{{ $task->id }}"
                                class="relative align-top transition-colors hover:bg-sky-50">
                                <td class="px-4 py-3">
                                    <a href="{{ $url }}" class="block break-words text-slate-800 after:absolute after:inset-0 hover:text-sky-700">
                                        <span class="mr-1 text-xs text-slate-400">#{{ $task->number }}</span>{{ $task->title }}
                                    </a>
                                    @if ($task->labels->isNotEmpty())
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach ($task->labels as $label)
                                                <span class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[11px] leading-4 text-slate-700"
                                                      style="background-color: {{ $label->color }}1f">
                                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
                                                    <span class="truncate">{{ $label->name }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    {{-- 狭い画面では隠れる列の中身をここに出す --}}
                                    <p class="mt-1 truncate text-xs text-slate-500 md:hidden">{{ $task->project->name }}</p>
                                    <x-due-chip :task="$task" />
                                </td>
                                <td class="hidden px-4 py-3 md:table-cell">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <x-project-icon :project="$task->project" size="xs" />
                                        <span class="truncate text-slate-700" title="{{ $task->project->name }}">{{ $task->project->name }}</span>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3 lg:table-cell">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $task->list->color }}"></span>
                                        <span class="truncate text-slate-700" title="{{ $task->list->name }}">{{ $task->list->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 text-xs font-medium {{ $task->status->textClass() }}">
                                        <x-task-status-icon :status="$task->status" class="h-4 w-4" />
                                        {{ $task->status->label() }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3 text-xs tabular-nums text-slate-600 sm:table-cell">
                                    @if ($task->started_on || $task->ended_on)
                                        {{ $task->periodLabel() }}
                                    @else
                                        <span class="text-slate-400">なし</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>

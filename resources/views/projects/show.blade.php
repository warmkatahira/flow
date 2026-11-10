@php
    use App\Enums\ProjectRole;

    $me = auth()->user();
    $isOwner = $project->roleOf($me) === ProjectRole::Owner;
@endphp

<x-layouts.app :title="$project->name" fill sidebar>
    {{-- プロジェクト内のタブ。ガントチャート・ファイルはこれから --}}
    <div x-data="{ tab: @js(in_array(request()->query('tab'), ['board', 'calendar', 'members', 'archive'], true) ? request()->query('tab') : 'board') }" class="flex min-h-0 flex-1 flex-col"
         x-project-live="{ projectId: {{ $project->id }}, boardUrl: @js(route('projects.board', $project)) }">
        <div class="border-b border-slate-200 bg-white">
            {{-- プロジェクト名は左のリストにあるので、見出しは置かない --}}
            <div class="flex items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                @include('projects.partials.tabs', ['archiveCount' => $archivedLists->count() + $archivedTasks->count()])

                <div class="flex shrink-0 items-center gap-2">
                    @include('projects.partials.board-filter')
                    <x-favorite-button :project="$project" />
                </div>
            </div>
        </div>

        {{-- ボードは画面の右端まで見せるので、右の余白は中身ごとに持たせる --}}
        <div class="flex min-h-0 flex-1 flex-col pl-4 pt-4 sm:pl-6 lg:pl-8">
            <div class="shrink-0 pr-4 sm:pr-6 lg:pr-8">
            <x-session-status :status="session('status')" />

            @if ($project->isArchived())
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                    <p>{{ $project->name }} は {{ $project->archived_at->format('Y/m/d') }} にアーカイブされました。読み取り専用です。</p>

                    @if ($isOwner)
                        <form autocomplete="off" method="POST" action="{{ route('projects.unarchive', $project) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1 text-xs font-semibold text-white hover:bg-amber-700">元に戻す</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- ボード --}}
            </div>

            @unless ($project->isArchived())
                @include('projects.partials.move-dialog')
                @include('projects.partials.list-edit-dialog')
                {{-- ?task= 付きのリンクで開いたら、そのタスクを開く --}}
                @include('projects.partials.task-dialog', [
                    'openUrl' => ($opened = $project->tasks()->active()->find(request()->integer('task'))) && $opened->isOnBoard()
                        ? route('tasks.show', $opened)
                        : null,
                ])
            @endunless

            <div x-show="tab === 'board'" data-live-board class="flex min-h-0 flex-1 flex-col">
                @include($project->isArchived() ? 'projects.partials.board-readonly' : 'projects.partials.board')
            </div>

            {{-- カレンダー --}}
            <div x-show="tab === 'calendar'" x-cloak class="flex min-h-0 flex-1 flex-col">
                @include('projects.partials.calendar')
            </div>

            {{-- アーカイブ --}}
            <div x-show="tab === 'archive'" x-cloak data-live-archive class="min-h-0 flex-1 overflow-y-auto pb-6 pr-4 sm:pr-6 lg:pr-8">
                @include('projects.partials.archive')
            </div>

            {{-- メンバー --}}
            <div x-show="tab === 'members'" x-cloak class="min-h-0 flex-1 overflow-y-auto pb-6 pr-4 sm:pr-6 lg:pr-8">
                <div class="max-w-3xl">
                <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-bold text-slate-700">参加者（{{ $project->users->count() }}人）</h2>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($project->users as $user)
                            @php
                                $role = ProjectRole::tryFrom($user->pivot->role);
                            @endphp
                            <li class="flex items-center gap-3 px-5 py-3">
                                <x-user-avatar :user="$user" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-slate-800">
                                        {{ $user->name }}
                                        @if ($me->is($user))
                                            <span class="ml-1 text-xs text-slate-400">(自分)</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                </div>
                                @if ($role)
                                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $role->badgeClass() }}">
                                        {{ $role->label() }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

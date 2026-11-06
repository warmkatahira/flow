@php
    use App\Enums\ProjectRole;

    $me = auth()->user();
    $isOwner = $project->roleOf($me) === ProjectRole::Owner;
@endphp

<x-layouts.app :title="$project->name" fill sidebar>
    {{-- プロジェクト内のタブ。ガントチャート・ファイルはこれから --}}
    <div x-data="{ tab: 'board' }" class="flex flex-1 flex-col"
         x-project-live="{ projectId: {{ $project->id }}, boardUrl: @js(route('projects.board', $project)) }">
        <div class="border-b border-slate-200 bg-white">
            {{-- プロジェクト名は左のリストにあるので、見出しは置かない --}}
            <div class="flex items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <nav class="flex min-w-0 gap-1 overflow-x-auto [scrollbar-width:none]" aria-label="プロジェクトのタブ">
                    <button type="button" @click="tab = 'board'"
                            :class="tab === 'board' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="inline-flex shrink-0 items-center gap-1.5 border-b-2 px-4 py-3 text-sm font-semibold transition">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M14 17h2.75A2.25 2.25 0 0019 14.75V5.25A2.25 2.25 0 0016.75 3H14v14zM12.5 3h-5v14h5V3zM3.25 3H6v14H3.25A2.25 2.25 0 011 14.75v-9.5A2.25 2.25 0 013.25 3z" /></svg>
                        ボード
                    </button>
                    <button type="button" @click="tab = 'members'"
                            :class="tab === 'members' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="inline-flex shrink-0 items-center gap-1.5 border-b-2 px-4 py-3 text-sm font-semibold transition">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z" /></svg>
                        メンバー
                    </button>
                    <button type="button" @click="tab = 'archive'"
                            :class="tab === 'archive' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="inline-flex shrink-0 items-center gap-1.5 border-b-2 px-4 py-3 text-sm font-semibold transition">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 3a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H2z" /><path fill-rule="evenodd" clip-rule="evenodd" d="M2 7.5h16l-.811 7.71a2 2 0 01-1.99 1.79H4.802a2 2 0 01-1.99-1.79L2 7.5zM7 11a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" /></svg>
                        アーカイブ
                        <span data-live-archive-count class="ml-0.5 font-normal text-slate-400">{{ $archivedLists->count() + $archivedTasks->count() }}</span>
                    </button>

                    @if ($isOwner && ! $project->isArchived())
                        <a href="{{ route('projects.edit', $project) }}"
                           class="inline-flex shrink-0 items-center gap-1.5 border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500 transition hover:text-slate-800">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" /></svg>
                            設定
                        </a>
                    @endif
                </nav>

                <x-favorite-button :project="$project" />
            </div>
        </div>

        <div class="flex flex-1 flex-col px-4 sm:px-6 lg:px-8 pt-4 pb-2">
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

            <div x-show="tab === 'board'" data-live-board class="flex flex-1 flex-col">
                @include($project->isArchived() ? 'projects.partials.board-readonly' : 'projects.partials.board')
            </div>

            {{-- アーカイブ --}}
            <div x-show="tab === 'archive'" x-cloak data-live-archive>
                @include('projects.partials.archive')
            </div>

            {{-- メンバー --}}
            <div x-show="tab === 'members'" x-cloak class="max-w-3xl">
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
</x-layouts.app>

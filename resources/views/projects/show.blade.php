@php
    use App\Enums\ProjectRole;

    $me = auth()->user();
    $isOwner = $project->roleOf($me) === ProjectRole::Owner;
@endphp

<x-layouts.app :title="$project->name" fill sidebar>
    <x-slot:header>
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-2">
                <x-project-icon :project="$project" size="sm" />
                <h1 class="truncate text-lg font-bold text-slate-800">{{ $project->name }}</h1>
                <x-favorite-button :project="$project" />
            </div>

            @if ($isOwner && ! $project->isArchived())
                <a href="{{ route('projects.edit', $project) }}"
                   class="shrink-0 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    設定
                </a>
            @endif
        </div>
    </x-slot:header>

    {{-- プロジェクト内のタブ。ガントチャート・ファイルはこれから --}}
    <div x-data="{ tab: 'board' }" class="flex flex-1 flex-col"
         x-project-live="{ projectId: {{ $project->id }}, boardUrl: @js(route('projects.board', $project)) }">
        <div class="border-b border-slate-200 bg-white">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="flex gap-1" aria-label="プロジェクトのタブ">
                    <button type="button" @click="tab = 'board'"
                            :class="tab === 'board' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="border-b-2 px-4 py-3 text-sm font-semibold transition">
                        ボード
                    </button>
                    <button type="button" @click="tab = 'members'"
                            :class="tab === 'members' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="border-b-2 px-4 py-3 text-sm font-semibold transition">
                        メンバー
                    </button>
                    <button type="button" @click="tab = 'archive'"
                            :class="tab === 'archive' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="border-b-2 px-4 py-3 text-sm font-semibold transition">
                        アーカイブ
                        <span data-live-archive-count class="ml-0.5 font-normal text-slate-400">{{ $archivedLists->count() + $archivedTasks->count() }}</span>
                    </button>
                </nav>
            </div>
        </div>

        <div class="flex flex-1 flex-col px-4 sm:px-6 lg:px-8 pt-6 pb-2">
            <x-session-status :status="session('status')" />

            @if ($project->isArchived())
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                    <p>このプロジェクトは {{ $project->archived_at->format('Y/m/d') }} にアーカイブされました。読み取り専用です。</p>

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

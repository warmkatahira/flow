{{-- プロジェクトの中身（システム管理者の閲覧用）。ボードとファイルだけ。リアルタイム更新のチャンネルには入らない --}}
@php
    $tabClass = 'inline-flex shrink-0 cursor-pointer items-center gap-1.5 border-b-2 px-4 py-3 text-sm font-semibold transition';
@endphp

<x-layouts.app :title="$project->name" fill>
    <div x-data="{ tab: @js(request()->query('tab') === 'files' ? 'files' : 'board'), fileQuery: '' }" class="flex min-h-0 flex-1 flex-col">
        <div class="shrink-0 space-y-3 border-b border-slate-200 bg-white px-4 pt-4 sm:px-6 lg:px-8">
            @include('admin.projects.partials.breadcrumb', ['items' => [
                [$user->name, route('admin.projects.user', $user)],
                [$project->name, null],
            ]])

            <div class="flex flex-wrap items-center gap-3">
                <x-project-icon :project="$project" />
                <h1 class="min-w-0 truncate text-lg font-bold text-slate-800">{{ $project->name }}</h1>
                @if ($project->isPersonal())
                    <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">My Project</span>
                @endif
                @if ($project->isArchived())
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">アーカイブ中</span>
                @endif
            </div>

            @include('admin.projects.partials.notice')

            <nav class="flex gap-1" aria-label="プロジェクトのタブ">
                <button type="button" @click="tab = 'board'" :class="tab === 'board' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'" class="{{ $tabClass }}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M14 17h2.75A2.25 2.25 0 0019 14.75V5.25A2.25 2.25 0 0016.75 3H14v14zM12.5 3h-5v14h5V3zM3.25 3H6v14H3.25A2.25 2.25 0 011 14.75v-9.5A2.25 2.25 0 013.25 3z" /></svg>
                    ボード
                </button>
                <button type="button" @click="tab = 'files'" :class="tab === 'files' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'" class="{{ $tabClass }}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z" /></svg>
                    ファイル
                    <span class="ml-0.5 font-normal text-slate-400">{{ $files->count() }}</span>
                </button>
            </nav>
        </div>

        <div class="flex min-h-0 flex-1 flex-col pl-4 pt-4 sm:pl-6 lg:pl-8">
            <div x-show="tab === 'board'" class="flex min-h-0 flex-1 flex-col">
                @include('projects.partials.board-readonly', [
                    'followedTaskIds' => collect(),
                    'taskUrl' => fn ($task) => route('admin.projects.task', [$user, $project, $task]),
                ])
            </div>

            <div x-show="tab === 'files'" x-cloak class="min-h-0 flex-1 overflow-y-auto pb-6 pr-4 sm:pr-6 lg:pr-8">
                <div class="mb-3 relative w-full max-w-xs">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" />
                    </svg>
                    <input type="search" x-model="fileQuery" placeholder="ファイル名で絞り込む" aria-label="ファイル名で絞り込む" autocomplete="off"
                           class="block w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                </div>

                @include('projects.partials.files', [
                    'fileUrl' => fn ($file) => route('admin.projects.attachment', $file),
                    'taskUrl' => fn ($task) => route('admin.projects.task', [$user, $project, $task]),
                ])
            </div>
        </div>
    </div>
</x-layouts.app>

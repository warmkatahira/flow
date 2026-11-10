{{-- My Project のボード（システム管理者の閲覧用）。リアルタイム更新のチャンネルには入らない --}}
<x-layouts.app :title="($project->personalUser?->name ?? '').' さんの My Project'" fill>
    <div class="flex min-h-0 flex-1 flex-col pl-4 pt-4 sm:pl-6 lg:pl-8">
        <div class="mb-4 shrink-0 space-y-3 pr-4 sm:pr-6 lg:pr-8">
            <a href="{{ route('admin.my-projects.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" />
                </svg>
                みんなの My Project
            </a>

            <div class="flex items-center gap-3">
                <x-project-icon :project="$project" />
                <h1 class="min-w-0 truncate text-lg font-bold text-slate-800">{{ $project->name }}</h1>
            </div>

            @include('admin.my-projects.partials.notice')
        </div>

        @include('projects.partials.board-readonly', [
            'followedTaskIds' => collect(),
            'taskUrl' => fn ($task) => route('admin.my-projects.task', [$project, $task]),
        ])
    </div>
</x-layouts.app>

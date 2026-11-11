<x-layouts.app :title="$user->name.' さんのプロジェクト'">
    <x-slot:header>
        <div class="space-y-2">
            @include('admin.projects.partials.breadcrumb', ['items' => [[$user->name, null]]])
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$user" />
                <h1 class="text-lg font-bold text-slate-800">{{ $user->name }} さんが参加しているプロジェクト</h1>
            </div>
        </div>
    </x-slot:header>

    <div class="px-4 sm:px-6 lg:px-8 py-8">
        @if ($projects->isEmpty())
            <div class="rounded-xl bg-white p-10 text-center text-sm text-slate-500 shadow-sm">参加しているプロジェクトはありません。</div>
        @else
            <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">プロジェクト</th>
                            <th class="px-4 py-3 text-left font-medium">役割</th>
                            <th class="px-4 py-3 text-right font-medium">参加者</th>
                            <th class="px-4 py-3 text-right font-medium">タスク</th>
                            <th class="px-4 py-3 text-left font-medium">最終更新</th>
                            <th class="px-4 py-3"><span class="sr-only">操作</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($projects as $project)
                            @php
                                $role = \App\Enums\ProjectRole::tryFrom($project->pivot->role);
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-project-icon :project="$project" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-slate-800">{{ $project->name }}</p>
                                            <div class="mt-0.5 flex flex-wrap gap-1">
                                                @if ($project->isPersonal())
                                                    <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">My Project</span>
                                                @endif
                                                @if ($project->isArchived())
                                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">アーカイブ中</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($role)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $role->badgeClass() }}">{{ $role->label() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $project->users_count }}人</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $project->tasks_count }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                    {{ $project->tasks_max_updated_at ? \Illuminate\Support\Carbon::parse($project->tasks_max_updated_at)->format('Y/m/d H:i') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.projects.show', [$user, $project]) }}"
                                       class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        中を見る
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>

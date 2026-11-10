<x-layouts.app title="みんなの My Project">
    <x-slot:header>
        <div>
            <h1 class="text-lg font-bold text-slate-800">みんなの My Project</h1>
            <p class="mt-1 text-xs text-slate-500">閲覧専用です。操作はできず、見ても本人への通知や既読などアプリ内の記録は残りません。</p>
        </div>
    </x-slot:header>

    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">ユーザー</th>
                        <th class="px-4 py-3 text-left font-medium">プロジェクト名</th>
                        <th class="px-4 py-3 text-right font-medium">タスク</th>
                        <th class="px-4 py-3 text-left font-medium">最終更新</th>
                        <th class="px-4 py-3 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        @php
                            $project = $user->personalProject;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-800">
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$user" />
                                    <div class="min-w-0">
                                        <p class="truncate">
                                            {{ $user->name }}
                                            @if ($user->trashed())
                                                <span class="ml-1 inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">削除済み</span>
                                            @elseif (! $user->is_active)
                                                <span class="ml-1 inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">無効</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            @if ($project)
                                <td class="px-4 py-3 text-slate-700">
                                    <div class="flex items-center gap-2">
                                        <x-project-icon :project="$project" size="xs" />
                                        <span class="min-w-0 truncate">{{ $project->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $project->tasks_count }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                    {{ $project->tasks_max_updated_at ? \Illuminate\Support\Carbon::parse($project->tasks_max_updated_at)->format('Y/m/d H:i') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.my-projects.show', $project) }}"
                                       class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        見る
                                    </a>
                                </td>
                            @else
                                <td colspan="4" class="px-4 py-3 text-slate-400">My Project はありません。</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>

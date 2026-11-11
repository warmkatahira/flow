@php
    use App\Enums\ProjectRole;
@endphp

<x-layouts.app title="アーカイブ">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">アーカイブ済みのプロジェクト</h1>
    </x-slot:header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if ($projects->isEmpty())
            <p class="rounded-xl bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                アーカイブ済みのプロジェクトはありません。
            </p>
        @else
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm">
                @foreach ($projects as $project)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <x-project-icon :project="$project" size="sm" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('projects.show', $project) }}"
                               class="block truncate text-sm font-semibold text-slate-800 hover:text-sky-700">
                                {{ $project->name }}
                            </a>
                            <p class="text-xs text-slate-500">{{ $project->archived_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }} にアーカイブ</p>
                        </div>
                        @if (ProjectRole::tryFrom($project->pivot->role) === ProjectRole::Owner)
                            <form autocomplete="off" method="POST" action="{{ route('projects.unarchive', $project) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-sky-700 hover:text-sky-900">元に戻す</button>
                            </form>
                            <form autocomplete="off" method="POST" action="{{ route('projects.destroy', $project) }}"
                                  onsubmit="return confirm(@js($project->name.' を完全に削除します。リストやタスクもすべて消え、元に戻せません。よろしいですか？'));">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">完全に削除</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>

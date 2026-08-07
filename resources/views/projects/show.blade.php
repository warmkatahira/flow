@php
    use App\Enums\ProjectRole;

    $me = auth()->user();
    $isOwner = $project->roleOf($me) === ProjectRole::Owner;
@endphp

<x-layouts.app :title="$project->name">
    <x-slot:header>
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-2" x-data
                 x-init="$store.favorites.ids = @js($project->isFavoritedBy($me) ? [$project->id] : [])">
                <x-project-icon :project="$project" size="sm" />
                <h1 class="truncate text-lg font-bold text-slate-800">{{ $project->name }}</h1>
                <x-favorite-button :project="$project" />
            </div>

            @if ($isOwner)
                <a href="{{ route('projects.edit', $project) }}"
                   class="shrink-0 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    設定
                </a>
            @endif
        </div>
    </x-slot:header>

    {{-- プロジェクト内のタブ。ガントチャート・ファイルはこれから --}}
    <div x-data="{ tab: 'board' }" class="flex-1">
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
                </nav>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <x-session-status :status="session('status')" />

            {{-- ボード --}}
            <div x-show="tab === 'board'">
                @if ($project->taskLists->isEmpty())
                    <p class="mb-4 text-sm text-slate-500">
                        リストを追加してカンバンを作りましょう。ToDo / 進行中 / 完了 のように並べます。
                    </p>
                @endif

                @include('projects.partials.board')
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
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-bold text-white">
                                    {{ mb_substr($user->name, 0, 1) }}
                                </span>
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

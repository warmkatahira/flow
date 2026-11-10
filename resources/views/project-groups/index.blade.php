<x-layouts.app title="グループ管理">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-bold text-slate-800">グループ管理</h1>
            <a href="{{ route('project-groups.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                グループを作成
            </a>
        </div>
    </x-slot:header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <p class="mb-4 text-xs text-slate-500">
            グループはプロジェクトをまとめるための名前です。所属は任意で、グループを削除しても
            所属していたプロジェクトは「グループなし」として残ります。<br>
            左の持ち手をドラッグすると並べ替えられます。この順番でダッシュボードに表示されます。
        </p>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            {{-- x-data がない場所だと Alpine がディレクティブを初期化しないので、空でも置いておく --}}
            <ul class="divide-y divide-slate-100" x-data
                x-sortable="{ url: @js(route('project-groups.reorder')), handle: '[data-handle]' }">
                @forelse ($groups as $group)
                    <li data-id="{{ $group->id }}" class="flex items-center gap-3 px-5 py-4">
                        <span data-handle aria-label="ドラッグして並べ替え"
                              class="shrink-0 cursor-grab rounded p-1 text-slate-300 transition hover:bg-slate-100 hover:text-slate-500">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M7 5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 15a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM16 5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM16 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM16 15a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                            </svg>
                        </span>
                        <span class="inline-block h-3 w-1 shrink-0 rounded-full bg-sky-500"></span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-800">{{ $group->name }}</span>

                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            <a href="{{ route('project-groups.edit', $group) }}" class="text-sky-600 hover:text-sky-800">編集</a>

                            <form autocomplete="off" method="POST" action="{{ route('project-groups.destroy', $group) }}"
                                  onsubmit="return confirm('{{ $group->name }} を削除します。所属しているプロジェクトはグループなしになります。よろしいですか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800">削除</button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">グループがありません。</li>
                @endforelse
            </ul>
        </div>

        <div class="mt-4">
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-600 hover:text-slate-900">ダッシュボードへ戻る</a>
        </div>
    </div>
</x-layouts.app>

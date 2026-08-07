<x-layouts.app title="プロジェクト編集">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">プロジェクト編集</h1>
    </x-slot:header>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm px-6 py-8">
            <form autocomplete="off" method="POST" action="{{ route('projects.update', $project) }}"
                  enctype="multipart/form-data" class="space-y-6">
                @method('PUT')
                @include('projects.partials.form', ['submitLabel' => '更新する'])
            </form>
        </div>

        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-6 py-5">
            <h2 class="text-sm font-bold text-rose-800">プロジェクトを削除</h2>
            <p class="mt-1 text-xs text-rose-700">
                削除すると参加者の割り当ても一緒に消えます。元に戻せません。
            </p>
            <form autocomplete="off" method="POST" action="{{ route('projects.destroy', $project) }}" class="mt-3"
                  onsubmit="return confirm('{{ $project->name }} を削除します。よろしいですか？');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">
                    削除する
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>

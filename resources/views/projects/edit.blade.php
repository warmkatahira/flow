<x-layouts.app :title="$project->name.' の設定'" sidebar>
    {{-- プロジェクトの画面と同じタブ。他のタブはプロジェクトの画面へ戻る --}}
    <div class="border-b border-slate-200 bg-white">
        <div class="flex items-center gap-4 px-4 sm:px-6 lg:px-8">
            @include('projects.partials.tabs', ['active' => 'settings', 'archiveCount' => $archiveCount])
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm px-6 py-8">
            <form autocomplete="off" method="POST" action="{{ route('projects.update', $project) }}"
                  enctype="multipart/form-data" class="space-y-6">
                @method('PUT')
                @include('projects.partials.form', ['submitLabel' => '更新する'])
            </form>
        </div>

        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-6 py-5">
            <h2 class="text-sm font-bold text-amber-800">プロジェクトをアーカイブ</h2>
            <p class="mt-1 text-xs text-amber-700">
                ダッシュボードに出なくなり、ボードは読み取り専用になります。データは残るので、いつでも元に戻せます。
            </p>
            <form autocomplete="off" method="POST" action="{{ route('projects.archive', $project) }}" class="mt-3"
                  data-archive-confirm="プロジェクト" data-name="{{ $project->name }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700">
                    アーカイブする
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>

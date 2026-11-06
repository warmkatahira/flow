{{-- アーカイブ中のプロジェクトのボード。見るだけなので操作の UI は持たない --}}
<div class="flex flex-1 items-start gap-4 overflow-x-auto pb-4" x-drag-scroll>
    @forelse ($project->taskLists as $list)
        <section class="flex w-72 shrink-0 flex-col rounded-xl bg-slate-200/70 shadow-sm ring-1 ring-slate-300/70">
            <div class="flex items-center gap-2 rounded-t-xl px-3 py-2 {{ $list->color->headerClass() }}">
                <span class="min-w-0 flex-1 truncate text-sm font-bold">{{ $list->name }}</span>
                <span class="text-xs font-medium opacity-70">{{ $list->tasks->count() }}</span>
            </div>

            <div class="min-h-8 space-y-2 p-2">
                @foreach ($list->tasks as $task)
                    <article class="flex items-start gap-2 rounded-lg bg-white p-2.5 shadow-xs ring-1 ring-slate-200">
                        <span class="mt-0.5 shrink-0 text-[10px] font-medium text-slate-400">#{{ $task->number }}</span>
                        <p class="min-w-0 flex-1 text-sm text-slate-800">{{ $task->title }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-sm text-slate-500">リストはありません。</p>
    @endforelse
</div>

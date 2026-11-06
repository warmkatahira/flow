<button type="button" @click="$store.boardFilter.toggle('labels', 0)"
        :class="$store.boardFilter.labels.includes(0) && 'bg-sky-50'"
        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition hover:bg-slate-100 text-slate-700">
    <span class="h-4 w-4 shrink-0 rounded-full bg-slate-300"></span>
    指定なし
</button>
@foreach ($project->labels as $label)
    <button type="button" @click="$store.boardFilter.toggle('labels', {{ $label->id }})"
            :class="$store.boardFilter.labels.includes({{ $label->id }}) && 'bg-sky-50'"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition hover:bg-slate-100 text-slate-700">
        <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
        <span class="min-w-0 truncate">{{ $label->name }}</span>
    </button>
@endforeach

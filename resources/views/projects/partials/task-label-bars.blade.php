{{-- カードのタグ。折り畳んだときは色のバー、「拡張する」で名前も出す --}}
@if ($task->labels->isNotEmpty())
    <div class="mt-1 flex flex-wrap gap-1 cards-expanded:hidden">
        @foreach ($task->labels as $label)
            <span title="{{ $label->name }}" class="h-1.5 w-9 rounded-full" style="background-color: {{ $label->color }}"></span>
        @endforeach
    </div>

    {{-- 文字はどの色の上でも読めるよう濃いグレーにし、背景はタグの色をうっすら敷く（末尾の 1f は透明度） --}}
    <div class="mt-1.5 hidden flex-wrap gap-1 cards-expanded:flex">
        @foreach ($task->labels as $label)
            <span class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[11px] leading-4 text-slate-700"
                  style="background-color: {{ $label->color }}1f">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
                <span class="truncate">{{ $label->name }}</span>
            </span>
        @endforeach
    </div>
@endif

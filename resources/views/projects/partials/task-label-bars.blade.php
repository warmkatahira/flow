{{-- カードのラベル。折り畳んでも見えるよう、色のバーで出す --}}
@if ($task->labels->isNotEmpty())
    <div class="mt-1 flex flex-wrap gap-1">
        @foreach ($task->labels as $label)
            <span title="{{ $label->name }}" class="h-1.5 w-9 rounded-full" style="background-color: {{ $label->color }}"></span>
        @endforeach
    </div>
@endif

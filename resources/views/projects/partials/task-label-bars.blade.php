{{--
    カードのタグ。折り畳んだときは色のバー、「拡張する」で名前の札になる。
    バーと札は同じ要素で、拡張・折り畳みのときに形を変える（resources/css/app.css の .card-tag）
--}}
@if ($task->labels->isNotEmpty())
    <div class="mt-1 flex flex-wrap gap-1">
        @foreach ($task->labels as $label)
            <span title="{{ $label->name }}" class="card-tag" style="--tag: {{ $label->color }}">{{ $label->name }}</span>
        @endforeach
    </div>
@endif

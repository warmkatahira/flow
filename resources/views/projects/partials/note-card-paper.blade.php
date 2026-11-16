{{--
    付箋の飾り（resources/css/app.css の .note-lift / .note-stamp）
    - 下の端の反り: 付箋の下に、両角だけ濃い影を落とす
    - 完了のハンコ: ステータスが完了なら、右下に「済」の赤いハンコを押す
--}}
<span class="note-lift" aria-hidden="true"></span>
@if ($task->status === \App\Enums\TaskStatus::Done)
    <span class="note-stamp" aria-hidden="true">済</span>
@endif

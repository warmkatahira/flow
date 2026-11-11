@props(['task'])

{{--
    期限が近いタスクの印（カードのタイトルの下。折り畳んでいても出す）
    期限切れ＝赤 / 今日まで＝オレンジ / 残り2日・残り3日＝黄（今日を1日目と数える）。完了・中止には出さない。
    日付が変わっても開いたままの画面で切り替わるよう、判定はブラウザ側（resources/js/app.js の dueChip）で行う。
--}}
@if ($task->ended_on && ! in_array($task->status, [\App\Enums\TaskStatus::Done, \App\Enums\TaskStatus::Canceled], true))
    <span x-data="dueChip(@js($task->ended_on->format('Y-m-d')), {
              overdue: 'bg-rose-100 text-rose-700',
              today: 'bg-orange-100 text-orange-700',
              soon: 'bg-yellow-100 text-yellow-800',
          })"
          x-show="tone" x-cloak :class="classes[tone]" data-due-chip
          class="mt-1.5 inline-flex max-w-full items-center gap-0.5 rounded-full py-px pl-1 pr-1.5 text-[11px] font-semibold leading-4">
        <svg class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" />
        </svg>
        <span class="truncate" x-text="text"></span>
    </span>
@endif

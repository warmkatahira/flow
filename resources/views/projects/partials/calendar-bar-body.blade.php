{{-- カレンダーの帯の中身（未読の点・番号・タイトル）。$task は帯のタスクを指す JS の式 --}}
<span x-show="$store.unread.hasTask({{ $task }}.id)" data-unread-dot title="未読の通知あり"
      class="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"></span>
<span class="shrink-0 text-[10px] text-slate-500" x-text="`#${ {{ $task }}.number }`"></span>
<span class="truncate" x-text="{{ $task }}.title"></span>

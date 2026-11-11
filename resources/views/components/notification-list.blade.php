@props(['notifications'])

@php
    use App\Models\TaskComment;
@endphp

{{-- メンション（TaskComment）とフォローしたタスクの動き（TaskNotification）を新しい順に混ぜて出す。種類はアバターの右下の印で見分ける --}}
@forelse ($notifications as $notification)
    @php
        $isMention = $notification instanceof TaskComment;
        $actor = $isMention ? $notification->user : $notification->actor;
        $readAt = $isMention ? $notification->pivot->read_at : $notification->read_at;
        $createdAt = $isMention ? $notification->pivot->created_at : $notification->created_at;
        $task = $notification->task;
    @endphp
    <a href="{{ $isMention ? route('notifications.open', $notification) : route('notifications.activities.open', $notification) }}"
       class="flex gap-2.5 border-b border-slate-100 px-4 py-2.5 text-left transition last:border-0 hover:bg-sky-100 {{ $readAt ? '' : 'bg-sky-50' }}">
        <span class="relative h-fit shrink-0">
            <x-user-avatar :user="$actor" size="xs" />
            <span class="absolute -bottom-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white text-sky-600 ring-1 ring-slate-200"
                  title="{{ $isMention ? 'メンション' : 'フォロー中のタスク' }}">
                @if ($isMention)
                    <span class="text-[9px] font-bold leading-none">@</span>
                @else
                    <x-follow-icon class="h-2.5 w-2.5" />
                @endif
            </span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm text-slate-700">
                {{ $actor->name }} さんが{{ $isMention ? 'メンションしました' : $notification->message() }}
            </span>
            <span class="block truncate text-xs text-slate-500">{{ $task->project->name }} / {{ $task->title }}</span>
            @if ($isMention)
                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $notification->bodyText() }}</span>
            @elseif ($notification->type === App\Enums\TaskActivity::Comment && $notification->detail)
                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $notification->detail }}</span>
            @endif
            <span class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400">
                {{ $createdAt->locale('ja')->isoFormat('YYYY/MM/DD(ddd) HH:mm') }}
                @if ($readAt)
                    <span class="rounded-full bg-slate-200 px-1.5 text-[10px] leading-4 text-slate-600">既読</span>
                @endif
            </span>
        </span>
    </a>
@empty
    <p class="px-4 py-6 text-center text-sm text-slate-400">通知はありません。</p>
@endforelse

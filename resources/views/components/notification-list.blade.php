@props(['comments'])

@forelse ($comments as $comment)
    <a href="{{ route('notifications.open', $comment) }}"
       class="flex gap-2.5 border-b border-slate-100 px-4 py-2.5 text-left transition last:border-0 hover:bg-sky-100 {{ $comment->pivot->read_at ? '' : 'bg-sky-50' }}">
        <x-user-avatar :user="$comment->user" size="xs" />
        <span class="min-w-0 flex-1">
            <span class="block text-sm text-slate-700">{{ $comment->user->name }} さんがメンションしました</span>
            <span class="block truncate text-xs text-slate-500">{{ $comment->task->project->name }} / {{ $comment->task->title }}</span>
            <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $comment->body }}</span>
            <span class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400">
                {{ $comment->created_at->format('Y/m/d H:i') }}
                @if ($comment->pivot->read_at)
                    <span class="rounded-full bg-slate-200 px-1.5 text-[10px] leading-4 text-slate-600">既読</span>
                @endif
            </span>
        </span>
    </a>
@empty
    <p class="px-4 py-6 text-center text-sm text-slate-400">通知はありません。</p>
@endforelse

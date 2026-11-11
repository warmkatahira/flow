@props(['project', 'current' => false, 'unread' => false])

{{--
    未読の通知があるときは名前を白い太字にし、右端に点を出す。
    最初の状態はサーバーで描き、そのあとは $store.unread（ベルが取り直す）で切り替える。
--}}
<a href="{{ route('projects.show', $project) }}" title="{{ $project->name }}"
   @if ($current) aria-current="page" @endif
   data-unread="{{ $unread ? 'true' : 'false' }}" :data-unread="$store.unread.hasProject({{ $project->id }}) ? 'true' : 'false'"
   {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm transition '.($current ? 'bg-sky-500 text-white' : 'text-slate-200 hover:bg-slate-700 data-[unread=true]:font-bold data-[unread=true]:text-white')]) }}>
    <x-project-icon :project="$project" size="xs" />
    <span class="min-w-0 flex-1 truncate">{{ $project->name }}</span>
    <span @unless ($unread) style="display: none" @endunless x-show="$store.unread.hasProject({{ $project->id }})" data-unread-dot
          class="h-2 w-2 shrink-0 rounded-full {{ $current ? 'bg-white' : 'bg-sky-400' }}">
        <span class="sr-only">未読の通知あり</span>
    </span>
</a>

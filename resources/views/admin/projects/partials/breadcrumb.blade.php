{{-- プロジェクト管理のパンくず。$items は [文字, URL か null] の並び --}}
<nav aria-label="パンくず" class="flex min-w-0 flex-wrap items-center gap-1 text-sm text-slate-500">
    <a href="{{ route('admin.projects.index') }}" class="hover:text-slate-800">プロジェクト管理</a>
    @foreach ($items as [$label, $url])
        <svg class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" />
        </svg>
        @if ($url)
            <a href="{{ $url }}" class="min-w-0 truncate hover:text-slate-800">{{ $label }}</a>
        @else
            <span class="min-w-0 truncate text-slate-700" aria-current="page">{{ $label }}</span>
        @endif
    @endforeach
</nav>

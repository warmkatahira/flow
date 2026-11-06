@props(['project', 'current' => false])

<a href="{{ route('projects.show', $project) }}" title="{{ $project->name }}"
   @if ($current) aria-current="page" @endif
   {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm transition '.($current ? 'bg-sky-500 text-white' : 'text-slate-200 hover:bg-slate-700')]) }}>
    <x-project-icon :project="$project" size="xs" />
    <span class="truncate">{{ $project->name }}</span>
</a>

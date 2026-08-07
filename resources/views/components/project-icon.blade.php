@props(['project', 'size' => 'md'])

@php
    $sizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-sm',
        'lg' => 'h-16 w-16 text-2xl',
        default => 'h-12 w-12 text-lg',
    };
@endphp

@if ($project->iconUrl())
    <img src="{{ $project->iconUrl() }}" alt=""
         {{ $attributes->merge(['class' => "shrink-0 rounded-xl object-cover {$sizeClass}"]) }}>
@else
    {{-- アイコン未設定のときは名前の頭文字。色は名前から決まるので常に同じ --}}
    <span {{ $attributes->merge(['class' => "shrink-0 inline-flex items-center justify-center rounded-xl font-bold text-white {$sizeClass} {$project->initialColorClass()}"]) }}
          aria-hidden="true">
        {{ $project->initial() }}
    </span>
@endif

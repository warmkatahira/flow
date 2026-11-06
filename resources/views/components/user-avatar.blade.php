@props(['user', 'size' => 'sm'])

@php
    $sizeClass = match ($size) {
        'xs' => 'h-7 w-7 text-xs',
        'lg' => 'h-24 w-24 text-3xl',
        default => 'h-8 w-8 text-xs',
    };
@endphp

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt=""
         {{ $attributes->merge(['class' => "shrink-0 rounded-full object-cover {$sizeClass}"]) }}>
@else
    {{-- アイコン未設定のときは名前の頭文字 --}}
    <span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-sky-600 font-bold text-white {$sizeClass}"]) }}
          aria-hidden="true">
        {{ mb_substr($user->name, 0, 1) }}
    </span>
@endif

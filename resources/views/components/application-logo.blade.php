@props([
    'animated' => false,
    // sm = ヘッダー / lg = ログイン画面
    'size' => 'sm',
    // 暗い背景の上に置くとき
    'inverted' => false,
])

@php
    $large = $size === 'lg';
@endphp

{{-- Righteous は 400 しか持たないので font-bold は付けない（合成ボールドで潰れる） --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center '.($large ? 'gap-4 h-24' : 'gap-2 h-8')]) }}>
    <x-flow-mark :animated="$animated" />
    <span class="font-logo {{ $large ? 'text-5xl' : 'text-xl' }} {{ $inverted ? 'text-white' : 'text-slate-800' }}">{{ config('app.name') }}</span>
</span>

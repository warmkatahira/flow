@props([
    // mark  = ロゴ用。透過背景にブランド色のマークだけ
    // badge = favicon / アプリアイコン用。青の角丸ベタに白抜き
    'variant' => 'mark',
    'animated' => false,
    // true にすると Tailwind のクラスを付けずに書き出す（favicon.svg の生成用）
    'standalone' => false,
])

@php
    $badge = $variant === 'badge';

    // ---- ここがマークの形の定義。ロゴも favicon もすべてこの座標から作る ----

    // カンバンの列。左から右へ積み上がっていく
    $bars = [
        ['x' => 5,    'y' => 13,  'h' => 11],
        ['x' => 13.5, 'y' => 8.5, 'h' => 15.5],
        ['x' => 22,   'y' => 4,   'h' => 20],
    ];

    // 波は 16 ユーザー単位を1周期として描く。-16 動かすと同じ形に戻る＝継ぎ目なくループする
    $waveY = $badge ? 24 : 26;
    $waveAmp = $badge ? 3 : 3.5;
    $wave = "M -32 {$waveY} q 4 -{$waveAmp} 8 0" . str_repeat(' t 8 0', 9);

    // バッジは角丸ベタの内側に余白がいるので、同じ列を中心に向かって縮める
    $barsTransform = $badge ? 'translate(16 16) scale(.78) translate(-16 -16)' : null;

    // 同じページに複数置いても id がぶつからないようにする
    $uid = uniqid('flow-mark-');
@endphp

<svg {{ $standalone ? $attributes->merge(['width' => 32, 'height' => 32]) : $attributes->merge(['class' => 'h-full w-auto aspect-square']) }}
     viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"
     role="img" aria-label="{{ config('app.name') }}">
    <defs>
        <linearGradient id="{{ $uid }}-g" gradientUnits="userSpaceOnUse"
                        @if ($badge) x1="0" y1="32" x2="32" y2="0" @else x1="5" y1="28" x2="27" y2="4" @endif>
            <stop offset="0" stop-color="{{ $badge ? '#0284c7' : '#0ea5e9' }}" /> {{-- sky-600 / sky-500 --}}
            <stop offset="1" stop-color="{{ $badge ? '#06b6d4' : '#22d3ee' }}" /> {{-- cyan-500 / cyan-400 --}}
        </linearGradient>
        @if ($badge)
            <clipPath id="{{ $uid }}-c"><rect width="32" height="32" rx="7.5" /></clipPath>
        @endif
    </defs>

    @if ($badge)
        <rect width="32" height="32" rx="7.5" fill="url(#{{ $uid }}-g)" />
    @endif

    <g @if ($badge) clip-path="url(#{{ $uid }}-c)" @endif>
        {{-- カンバンの列 --}}
        <g fill="{{ $badge ? '#fff' : "url(#{$uid}-g)" }}" @if ($barsTransform) transform="{{ $barsTransform }}" @endif>
            @foreach ($bars as $bar)
                <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] }}" width="5" height="{{ $bar['h'] }}" rx="2.5" />
            @endforeach
        </g>

        {{-- 列の足元を流れていく波 --}}
        <g @class(['animate-flow-mark-wave' => $animated])>
            <path d="{{ $wave }}" fill="none" stroke-linecap="round"
                  stroke="{{ $badge ? '#fff' : '#0369a1' }}" {{-- sky-700 --}}
                  @if ($badge) stroke-opacity=".7" @endif
                  stroke-width="2.2" />
        </g>
    </g>
</svg>

@php
    // 端（x=0 と x=1440）の高さと傾きを揃えてあるので、横に並べても継ぎ目が出ない波
    $wavePath = 'M0,160 C120,100 240,100 360,160 C480,220 600,220 720,160 C840,100 960,100 1080,160 '
        .'C1200,220 1320,220 1440,160 L1440,320 L0,320 Z';

    // 奥から手前へ、速度と色を変えて重ねる
    $waves = [
        ['animation' => 'animate-flow-wave-slow', 'fill' => 'fill-sky-200/70', 'height' => 'h-56'],
        ['animation' => 'animate-flow-wave-reverse', 'fill' => 'fill-sky-300/50', 'height' => 'h-44'],
        ['animation' => 'animate-flow-wave', 'fill' => 'fill-sky-500/25', 'height' => 'h-32'],
    ];

    // 波の上を流れるタスクカード。delay をマイナスにして、表示した瞬間から画面全体に散らばらせる
    // [横幅px, 下からの位置, 横に流れる秒数, 開始位置(マイナス), 揺れる秒数, ラベル色]
    $cards = [
        [80, '7.5rem', 46, -6, 7.0, 'bg-sky-400/70'],
        [64, '3rem', 34, -13, 5.4, 'bg-emerald-400/70'],
        [56, '11rem', 54, -30, 8.6, 'bg-amber-400/70'],
        [88, '1.5rem', 60, -44, 9.4, 'bg-rose-400/60'],
        [64, '9rem', 40, -21, 6.2, 'bg-indigo-400/60'],
        [72, '5rem', 50, -35, 7.8, 'bg-sky-500/60'],
        [56, '13rem', 38, -9, 5.8, 'bg-emerald-400/60'],
    ];

    // 隙間に流す小さな泡（カードだけだと寂しいので奥行き用）
    // [大きさpx, 下からの位置, 横に流れる秒数, 開始位置(マイナス), 揺れる秒数, 色]
    $bubbles = [
        [10, '4rem', 26, -9, 5.0, 'bg-white/70'],
        [7, '8rem', 22, -3, 4.2, 'bg-white/80'],
        [14, '2rem', 44, -28, 8.0, 'bg-white/50'],
        [8, '12rem', 30, -17, 5.5, 'bg-sky-200/60'],
        [11, '6rem', 36, -24, 6.4, 'bg-white/60'],
    ];
@endphp

<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-linear-to-b from-slate-50 via-sky-50 to-sky-100"
     aria-hidden="true">
    {{-- ゆっくり漂う光 --}}
    <div class="absolute -top-40 -left-32 h-[32rem] w-[32rem] rounded-full bg-sky-300/40 blur-3xl animate-flow-drift-slow"></div>
    <div class="absolute top-1/4 -right-40 h-[28rem] w-[28rem] rounded-full bg-cyan-300/35 blur-3xl animate-flow-drift"></div>
    <div class="absolute -bottom-40 left-1/4 h-[26rem] w-[26rem] rounded-full bg-indigo-300/30 blur-3xl animate-flow-drift-reverse"></div>

    {{-- 下から流れ続ける波 --}}
    @foreach ($waves as $wave)
        <div class="absolute inset-x-0 bottom-0 flex w-[200%] {{ $wave['animation'] }}">
            @for ($i = 0; $i < 2; $i++)
                <svg class="w-1/2 {{ $wave['height'] }} {{ $wave['fill'] }}"
                     viewBox="0 0 1440 320" preserveAspectRatio="none">
                    <path d="{{ $wavePath }}" />
                </svg>
            @endfor
        </div>
    @endforeach

    {{-- 波の上を流れていく小さな泡 --}}
    @foreach ($bubbles as [$size, $bottom, $duration, $delay, $bob, $color])
        <div class="absolute left-0 animate-flow-float"
             style="bottom: {{ $bottom }}; animation-duration: {{ $duration }}s; animation-delay: {{ $delay }}s">
            <div class="animate-flow-float-bob" style="animation-duration: {{ $bob }}s; animation-delay: {{ $delay }}s">
                <div class="{{ $color }} rounded-full shadow-sm shadow-sky-900/5 ring-1 ring-inset ring-white/60"
                     style="width: {{ $size }}px; height: {{ $size }}px"></div>
            </div>
        </div>
    @endforeach

    {{-- 波の上を流れていくタスクカード --}}
    @foreach ($cards as [$width, $bottom, $duration, $delay, $bob, $label])
        <div class="absolute left-0 animate-flow-float"
             style="bottom: {{ $bottom }}; animation-duration: {{ $duration }}s; animation-delay: {{ $delay }}s">
            <div class="animate-flow-float-bob" style="animation-duration: {{ $bob }}s; animation-delay: {{ $delay }}s">
                <div class="animate-flow-float-sway" style="animation-duration: {{ $bob * 1.4 }}s; animation-delay: {{ $delay }}s">
                    <div class="rounded-lg bg-white/75 p-2 shadow-md shadow-sky-900/10 ring-1 ring-white/70 backdrop-blur-[1px]"
                         style="width: {{ $width }}px">
                        <div class="{{ $label }} h-1 w-2/5 rounded-full"></div>
                        <div class="mt-1.5 h-1 w-full rounded-full bg-slate-400/40"></div>
                        <div class="mt-1 h-1 w-3/5 rounded-full bg-slate-400/30"></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

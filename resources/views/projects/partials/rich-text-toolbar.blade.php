{{--
    書式付きエディタのツールバー（resources/js/rich-text.js の formatting を持つコンポーネントの中に置く）
    押してもエディタからフォーカスが外れないよう、ボタンは mousedown を止める
--}}
@php
    use App\Support\RichText;

    $buttonClass = 'flex h-7 min-w-7 items-center justify-center rounded-md px-1.5 text-sm text-slate-600 transition hover:bg-slate-100';
    $menuClass = 'absolute left-0 top-full z-30 mt-1 rounded-xl bg-white p-2 shadow-xl ring-1 ring-slate-200';
@endphp

<div class="flex flex-wrap items-center gap-0.5" role="toolbar" aria-label="書式" @click.outside="menu = null">
    @foreach ([
        'bold' => ['太字', 'Ctrl+B', 'font-bold'],
        'italic' => ['斜体', 'Ctrl+I', 'italic font-serif'],
        'underline' => ['下線', 'Ctrl+U', 'underline'],
        'strike' => ['取り消し線', 'Ctrl+Shift+S', 'line-through'],
    ] as $mark => [$label, $shortcut, $style])
        <button type="button" @mousedown.prevent @click="toggle('{{ $mark }}')"
                title="{{ $label }}（{{ $shortcut }}）" aria-label="{{ $label }}" :aria-pressed="marks.{{ $mark }} ? 'true' : 'false'"
                :class="marks.{{ $mark }} && 'bg-sky-100 text-sky-700 hover:bg-sky-100'"
                class="{{ $buttonClass }}">
            <span class="{{ $style }}">{{ ['bold' => 'B', 'italic' => 'I', 'underline' => 'U', 'strike' => 'S'][$mark] }}</span>
        </button>
    @endforeach

    <span class="mx-1 h-4 w-px bg-slate-200" aria-hidden="true"></span>

    {{-- 文字色 --}}
    <div class="relative">
        <button type="button" @mousedown.prevent @click="toggleMenu('color')"
                title="文字の色" aria-label="文字の色" aria-haspopup="menu" :aria-expanded="menu === 'color' ? 'true' : 'false'"
                class="{{ $buttonClass }} flex-col gap-0 leading-none">
            <span class="text-sm font-bold">A</span>
            <span class="mt-0.5 h-1 w-4 rounded-full" :style="{ backgroundColor: marks.color ?? '#1e293b' }"></span>
        </button>

        <div x-show="menu === 'color'" x-cloak role="menu" class="{{ $menuClass }} grid w-max grid-cols-5 gap-1">
            <button type="button" @mousedown.prevent @click="setStyle('color', null)" role="menuitem" title="標準" aria-label="標準の色"
                    class="flex h-7 w-7 items-center justify-center rounded-md text-sm font-bold text-slate-800 ring-1 ring-slate-200 hover:bg-slate-100">A</button>
            @foreach (RichText::TEXT_COLORS as $name => $value)
                <button type="button" @mousedown.prevent @click="setStyle('color', '{{ $value }}')" role="menuitem" title="{{ $name }}" aria-label="文字色：{{ $name }}"
                        :class="marks.color === '{{ $value }}' && 'ring-2 ring-sky-500'"
                        class="flex h-7 w-7 items-center justify-center rounded-md text-sm font-bold hover:bg-slate-100" style="color: {{ $value }}">A</button>
            @endforeach
        </div>
    </div>

    {{-- 背景色 --}}
    <div class="relative">
        <button type="button" @mousedown.prevent @click="toggleMenu('background')"
                title="文字の背景色" aria-label="文字の背景色" aria-haspopup="menu" :aria-expanded="menu === 'background' ? 'true' : 'false'"
                class="{{ $buttonClass }}">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M13.8 2.3a1 1 0 011.4 0l2.5 2.5a1 1 0 010 1.4l-7.4 7.4-3.9.6.6-3.9 6.8-8z" />
                <rect x="2" y="15.5" width="16" height="3" rx="1" :style="{ fill: marks.backgroundColor ?? '#fef08a' }" />
            </svg>
        </button>

        <div x-show="menu === 'background'" x-cloak role="menu" class="{{ $menuClass }} grid w-max grid-cols-5 gap-1">
            <button type="button" @mousedown.prevent @click="setStyle('backgroundColor', null)" role="menuitem" title="なし" aria-label="背景色なし"
                    class="relative h-7 w-7 overflow-hidden rounded-md ring-1 ring-slate-200 hover:bg-slate-100">
                <span class="absolute left-1/2 top-1/2 h-px w-8 -translate-x-1/2 -translate-y-1/2 -rotate-45 bg-rose-400"></span>
            </button>
            @foreach (RichText::BACKGROUND_COLORS as $name => $value)
                <button type="button" @mousedown.prevent @click="setStyle('backgroundColor', '{{ $value }}')" role="menuitem" title="{{ $name }}" aria-label="背景色：{{ $name }}"
                        :class="marks.backgroundColor === '{{ $value }}' && 'ring-2 ring-sky-500'"
                        class="h-7 w-7 rounded-md" style="background-color: {{ $value }}"></button>
            @endforeach
        </div>
    </div>

    {{-- 大きさ --}}
    <div class="relative">
        <button type="button" @mousedown.prevent @click="toggleMenu('size')"
                title="文字の大きさ" aria-label="文字の大きさ" aria-haspopup="menu" :aria-expanded="menu === 'size' ? 'true' : 'false'"
                class="{{ $buttonClass }} gap-0.5">
            <span class="text-xs">あ</span><span class="text-base leading-none">あ</span>
        </button>

        <div x-show="menu === 'size'" x-cloak role="menu" class="{{ $menuClass }} w-32 py-1.5">
            @foreach (['小' => RichText::FONT_SIZES['小'], '標準' => null, '大' => RichText::FONT_SIZES['大'], '特大' => RichText::FONT_SIZES['特大']] as $name => $value)
                <button type="button" @mousedown.prevent @click="setStyle('fontSize', @js($value))" role="menuitem"
                        :class="marks.fontSize === @js($value) && 'bg-sky-50 text-sky-700'"
                        class="block w-full rounded-md px-2 py-1 text-left text-sm text-slate-700 hover:bg-slate-100">
                    <span @if ($value) style="font-size: {{ $value }}" @endif>{{ $name }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <span class="mx-1 h-4 w-px bg-slate-200" aria-hidden="true"></span>

    <button type="button" @mousedown.prevent @click="clearFormatting()" title="書式を消す" aria-label="書式を消す" class="{{ $buttonClass }}">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <path d="M5 4h10M10 4l-3 12M3 17l14-14" />
        </svg>
    </button>
</div>

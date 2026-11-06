@props(['title', 'buttonClass' => ''])

{{--
    リストとタスクの ⋯ メニュー（resources/js/app.js の boardMenu）。
    ボードは横スクロールで外へはみ出せないので、画面に対して固定で出す。
    中の操作がドラッグにならないよう、押した合図は Sortable へ渡さない。
--}}
<div x-data="boardMenu" {{ $attributes->merge(['class' => 'shrink-0']) }}
     :data-menu-open="menuOpen ? '' : null"
     @pointerdown.stop
     @click.outside="menuOpen = false"
     @submit="menuOpen = false"
     @keydown.escape.window="menuOpen = false"
     @scroll.window.capture="menuOpen = false"
     @resize.window="menuOpen = false">
    <button type="button" x-ref="trigger" @click="toggle()" aria-label="{{ $title }}" aria-haspopup="menu"
            :aria-expanded="menuOpen ? 'true' : 'false'" class="block rounded transition {{ $buttonClass }}">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM11.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM17 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
        </svg>
    </button>

    <div x-show="menuOpen" x-cloak x-ref="panel" role="menu"
         class="fixed z-50 w-52 cursor-default rounded-xl bg-white py-2 shadow-xl ring-1 ring-slate-200">
        <p class="px-4 py-1.5 text-sm font-bold text-slate-500">{{ $title }}</p>
        {{ $slot }}
    </div>
</div>

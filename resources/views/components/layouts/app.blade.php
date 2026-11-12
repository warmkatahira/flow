@props([
    'title' => null,
    // 中身を画面の高さぴったりに収めるとき（ページ全体はスクロールさせない）
    'fill' => false,
    // 左のプロジェクトリストを出すか（プロジェクトの画面だけ）
    'sidebar' => false,
])

<!DOCTYPE html>
{{-- data-cards はカードの拡張・折り畳み（resources/js/app.js の cardView）。最初は畳んだ状態 --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-cards="collapsed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' | '.config('app.name') : config('app.name') }}</title>
    <x-favicon />
    {{-- 畳んだ状態で開いたときに一瞬開いて見えないよう、描画の前に戻す --}}
    <script>
        try {
            if (localStorage.getItem('flow.sidebar.collapsed') === '1') {
                document.documentElement.dataset.sidebar = 'closed';
            }

            if (localStorage.getItem('flow.cards.expanded') === '1') {
                document.documentElement.dataset.cards = 'expanded';
            }
        } catch {
            // ブラウザの保存領域が使えなければ開いたまま
        }
    </script>
    {{-- @vite だけでは自己ホストしたフォントが読み込まれない --}}
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-800">
    <div class="flex min-h-screen">
        @if ($sidebar)
            <x-sidebar />
        @endif

        {{-- min-w-0 がないとボードの幅に引っ張られて、横スクロールがボードの中に収まらない --}}
        <div @class(['flex min-w-0 flex-1 flex-col', 'h-dvh overflow-hidden' => $fill, 'min-h-screen' => ! $fill])>
            {{-- ページ全体がスクロールする画面でも、ナビゲーションと見出しは上に留めて見えたままにする --}}
            <div @class(['shrink-0', 'sticky top-0 z-30' => ! $fill])>
                <x-navigation :has-sidebar="$sidebar" />

                @isset($header)
                    <header class="bg-white border-b border-slate-200">
                        <div class="px-4 sm:px-6 lg:px-8 py-4">
                            {{ $header }}
                        </div>
                    </header>
                @endisset
            </div>

            <main @class(['flex-1', 'flex min-h-0 flex-col' => $fill])>
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-toast />
    <x-archive-confirm />
</body>
</html>

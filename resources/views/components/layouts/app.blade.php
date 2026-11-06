@props([
    'title' => null,
    // 中身を画面の下まで伸ばすとき
    'fill' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        {{-- パスワードの変更が済むまでは他の画面へ行けないので出さない --}}
        @unless (auth()->user()->must_change_password)
            <x-sidebar />
        @endunless

        {{-- min-w-0 がないとボードの幅に引っ張られて、横スクロールがボードの中に収まらない --}}
        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <x-navigation />

            @isset($header)
                <header class="bg-white border-b border-slate-200">
                    <div class="px-4 sm:px-6 lg:px-8 py-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main @class(['flex-1', 'flex flex-col' => $fill])>
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-800">
    <x-flow-background />

    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10">
        <a href="{{ route('login') }}" class="mb-6 animate-flow-rise">
            <x-application-logo class="h-9" />
        </a>
        <div class="w-full sm:max-w-md rounded-2xl bg-white/85 px-6 py-8 shadow-xl shadow-sky-900/5 ring-1 ring-white/70 backdrop-blur-md sm:px-8 animate-flow-rise"
             style="animation-delay: 0.1s">
            {{ $slot }}
        </div>
    </div>
</body>
</html>

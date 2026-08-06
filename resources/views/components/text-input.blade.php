@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge([
    'class' => 'block w-full rounded-lg border-slate-300 border px-3 py-2 text-slate-800 shadow-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none disabled:bg-slate-100',
]) }}>

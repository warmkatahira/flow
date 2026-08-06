<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>

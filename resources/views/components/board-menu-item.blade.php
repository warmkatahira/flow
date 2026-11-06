{{-- type は既定で button。送信するときだけ submit を渡す --}}
<button {{ $attributes->merge(['type' => 'button', 'role' => 'menuitem', 'class' => 'block w-full px-4 py-1.5 text-left text-sm text-slate-700 transition hover:bg-slate-100']) }}>
    {{ $slot }}
</button>

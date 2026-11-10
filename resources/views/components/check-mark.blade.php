{{--
    チェックボックスの見た目。直前に class="peer sr-only" の input[type=checkbox] を置き、両方を label で包む
--}}
<span {{ $attributes->merge(['class' => 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 border-slate-300 bg-white text-transparent transition peer-checked:border-sky-500 peer-checked:bg-sky-500 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-sky-500/40 peer-disabled:opacity-60']) }} aria-hidden="true">
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" />
    </svg>
</span>

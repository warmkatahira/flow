@props(['value' => null, 'required' => false])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700 mb-1']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="ml-1 text-xs text-rose-600">必須</span>
    @endif
</label>

@props(['value' => App\Enums\ListColor::Sky->value])

{{-- リストの色。プリセットから選ぶか、ピッカーかカラーコードで好きな色にする --}}
<div x-data="{ color: @js($value) }" class="space-y-2">
    <div class="flex flex-wrap gap-1.5">
        @foreach (App\Enums\ListColor::cases() as $preset)
            <button type="button" @click="color = @js($preset->value)"
                    :class="color.toLowerCase() === @js($preset->value) && 'ring-2 ring-slate-700'"
                    style="background-color: {{ $preset->value }}"
                    title="{{ $preset->label() }}" aria-label="{{ $preset->label() }}"
                    class="h-6 w-6 rounded-full ring-offset-1"></button>
        @endforeach
    </div>

    <div class="flex items-center gap-2">
        <input autocomplete="off" type="color" x-model="color" aria-label="好きな色を選ぶ"
               class="h-8 w-10 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5">
        <input autocomplete="off" type="text" name="color" x-model="color" required maxlength="7"
               pattern="#[0-9a-fA-F]{6}" title="#00a6f4 のようなカラーコード" aria-label="カラーコード"
               class="block w-full rounded-lg border border-slate-300 px-2 py-1 font-mono text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
    </div>
</div>

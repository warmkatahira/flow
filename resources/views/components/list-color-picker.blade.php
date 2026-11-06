@props(['value' => App\Enums\ListColor::Sky->value])

{{--
    リストの色。プリセットから選ぶか、カラーコードかピッカーで好きな色にする。
    x-model を付ければ外から色を渡せる（リストの編集ダイアログ）。
    同じ要素の x-model は内側のスコープで評価されるので、中の変数名は外と変えておく。
--}}
<div x-data="{ picked: @js($value) }" x-modelable="picked" {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-x-4 gap-y-2']) }}>
    <div class="flex flex-wrap gap-1.5">
        @foreach (App\Enums\ListColor::cases() as $preset)
            <button type="button" @click="picked = @js($preset->value)"
                    :class="picked.toLowerCase() === @js($preset->value) && 'ring-2 ring-slate-700'"
                    style="background-color: {{ $preset->value }}"
                    title="{{ $preset->label() }}" aria-label="{{ $preset->label() }}"
                    class="h-6 w-6 rounded-md ring-offset-1"></button>
        @endforeach
    </div>

    <div class="relative w-32">
        <input autocomplete="off" type="text" name="color" x-model="picked" required maxlength="7"
               pattern="#[0-9a-fA-F]{6}" title="#00a6f4 のようなカラーコード" aria-label="カラーコード"
               class="block w-full rounded-lg border border-slate-300 py-1.5 pl-3 pr-9 font-mono text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
        <input autocomplete="off" type="color" x-model="picked" aria-label="好きな色を選ぶ"
               class="absolute right-1.5 top-1/2 h-6 w-6 -translate-y-1/2 cursor-pointer appearance-none rounded-md border-0 bg-transparent p-0 [&::-moz-color-swatch]:rounded-md [&::-moz-color-swatch]:border-0 [&::-webkit-color-swatch]:rounded-md [&::-webkit-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0">
    </div>
</div>

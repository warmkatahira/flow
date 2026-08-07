@props(['project'])

{{--
    お気に入りの星。押した瞬間に見た目が変わり、通信は裏で走る（resources/js/app.js の store）。
    カード全体がリンクになっている場所でも押せるよう、クリックは伝播させない。
--}}
{{-- どこに置いても動くよう、自前で Alpine のスコープを持つ --}}
<button type="button" x-data
        @click.stop.prevent="$store.favorites.toggle({{ $project->id }}, @js(route('projects.favorite', $project)))"
        :aria-pressed="$store.favorites.has({{ $project->id }}) ? 'true' : 'false'"
        aria-label="お気に入り"
        {{ $attributes->merge(['class' => 'shrink-0 rounded-lg p-1.5 transition hover:bg-amber-50']) }}>
    <svg class="h-5 w-5 transition-colors" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.5"
         :class="$store.favorites.has({{ $project->id }})
            ? 'fill-amber-400 text-amber-400'
            : 'fill-none text-slate-300 hover:text-amber-400'"
         aria-hidden="true">
        <path stroke-linejoin="round"
              d="M10 2.5l2.35 4.76 5.25.77-3.8 3.7.9 5.23L10 14.49l-4.7 2.47.9-5.23-3.8-3.7 5.25-.77L10 2.5z" />
    </svg>
</button>

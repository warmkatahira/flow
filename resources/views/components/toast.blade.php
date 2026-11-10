{{--
    アーカイブ・削除をしたことを知らせる。5秒で消える。
    しゃぼん玉のように浮かんで出てきて、消えるときははじけて小さな泡が散る（resources/css/app.css の toast-bubble）。
    session('toast') = ['message' => 文言, 'url' => 取り消しの送信先, 'ids' => まとめて取り消すときの id]
    取り消せない操作（完全な削除など）は url を渡さない。
--}}
@if ($toast = session('toast'))
    <div x-data="{
            show: true,
            popping: false,
            pop() {
                if (this.popping) return;
                this.popping = true;
                setTimeout(() => this.show = false, 550);
            },
         }"
         x-init="setTimeout(() => pop(), 5000)" x-show="show"
         class="fixed bottom-6 left-4 right-4 z-50 sm:right-auto">
        <div role="status" :class="popping && 'is-popping'"
             class="toast-bubble flex items-center gap-4 rounded-xl bg-sky-500 py-2.5 pl-4 pr-3 text-sm text-white shadow-lg">
            <span class="min-w-0 flex-1">{{ $toast['message'] }}</span>

            @isset($toast['url'])
                <form autocomplete="off" method="POST" action="{{ $toast['url'] }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="undo" value="1">
                    @foreach ($toast['ids'] ?? [] as $id)
                        <input type="hidden" name="ids[]" value="{{ $id }}">
                    @endforeach
                    <button type="submit" class="rounded-lg bg-white px-5 py-1.5 text-sky-600 transition hover:bg-sky-50">キャンセル</button>
                </form>
            @endisset

            <button type="button" @click="pop()" aria-label="閉じる" class="shrink-0 rounded-lg p-1 transition hover:bg-white/20">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>

        {{-- はじけたときに散る泡 --}}
        <div class="toast-bubble-pop" aria-hidden="true">
            @foreach ([[-70, -34], [-30, -46], [20, -50], [64, -30], [80, 6], [-84, 10], [40, 30], [-40, 30]] as [$x, $y])
                <i style="--dx: {{ $x }}px; --dy: {{ $y }}px"></i>
            @endforeach
        </div>
    </div>
@endif

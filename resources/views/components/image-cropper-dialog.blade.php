{{--
    画像の表示エリアを選ぶポップアップ（resources/js/image-cropper.js の imageCropper の中に置く）
    枠の外は暗くし、枠の中だけを切り抜く。round のときは枠を丸く見せる（切り抜くのは正方形）
--}}
<dialog x-ref="cropper" @cancel.prevent="cancel()"
        class="m-auto w-[26rem] max-w-[calc(100vw-2rem)] rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
    <div class="px-6 pb-6 pt-5">
        <p class="text-center text-sm font-bold text-slate-700">画像表示エリアを変更</p>
        <p class="mt-1 text-center text-xs text-slate-500">ドラッグで位置、スライダーかホイールで大きさを調整します</p>

        {{-- 枠の外は暗くする。枠の中が切り抜かれる --}}
        <div class="relative mx-auto mt-4 cursor-grab touch-none overflow-hidden rounded-lg bg-slate-700 select-none active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
             :style="`width: ${STAGE_WIDTH}px; height: ${STAGE_HEIGHT}px;`"
             tabindex="0" role="application" aria-label="画像の表示エリア。矢印キーで動かせます"
             @pointerdown.prevent="start($event)" @pointermove="move($event)" @pointerup="end()" @pointercancel="end()"
             @wheel.prevent="wheel($event)"
             @keydown.left.prevent="nudge(-5, 0)" @keydown.right.prevent="nudge(5, 0)" @keydown.up.prevent="nudge(0, -5)" @keydown.down.prevent="nudge(0, 5)">
            <img x-ref="image" :src="src" alt="" draggable="false" :style="imageStyle" class="pointer-events-none absolute left-0 top-0 max-w-none origin-top-left">
            <div :class="round ? 'rounded-full' : 'rounded-xl'" class="pointer-events-none absolute ring-2 ring-white shadow-[0_0_0_9999px_rgb(15_23_42/0.55)]"
                 :style="`left: ${frameLeft}px; top: ${frameTop}px; width: ${FRAME}px; height: ${FRAME}px;`"></div>
        </div>

        <div class="mx-auto mt-4 flex max-w-64 items-center gap-3 text-slate-400">
            <button type="button" @click="zoomTo(zoom - 10)" aria-label="小さくする" class="rounded p-0.5 transition hover:text-slate-700">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.69l-2.22-2.219a.75.75 0 00-1.06 0l-1.91 1.909.47.47a.75.75 0 11-1.06 1.06L6.53 8.091a.75.75 0 00-1.06 0l-2.97 2.97zM12 7a1 1 0 11-2 0 1 1 0 012 0z" />
                </svg>
            </button>
            <input type="range" min="0" max="100" step="1" :value="zoom" @input="zoomTo($event.target.value)" aria-label="大きさ"
                   class="h-1.5 flex-1 cursor-pointer accent-sky-500">
            <button type="button" @click="zoomTo(zoom + 10)" aria-label="大きくする" class="rounded p-0.5 transition hover:text-slate-700">
                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.69l-2.22-2.219a.75.75 0 00-1.06 0l-1.91 1.909.47.47a.75.75 0 11-1.06 1.06L6.53 8.091a.75.75 0 00-1.06 0l-2.97 2.97zM12 7a1 1 0 11-2 0 1 1 0 012 0z" />
                </svg>
            </button>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <button type="button" @click="cancel()"
                    class="rounded-xl bg-sky-50 py-2.5 text-sm font-semibold text-sky-600 transition hover:bg-sky-100">キャンセル</button>
            <button type="button" @click="save()"
                    class="rounded-xl bg-sky-500 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-600">保存</button>
        </div>
    </div>
</dialog>

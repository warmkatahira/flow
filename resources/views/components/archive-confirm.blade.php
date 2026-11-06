{{--
    アーカイブの確認（resources/js/app.js の archiveConfirm）。
    data-archive-confirm="タスク" data-name="名前" を付けたフォームの送信で開く。補足は data-note に書く。
--}}
<div x-data="archiveConfirm" @submit.window.capture="ask($event)">
    <dialog x-ref="dialog" @click.self="$refs.dialog.close()"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white shadow-xl outline-none backdrop:bg-slate-900/40">
        <div class="relative p-8">
            <h2 class="text-center text-lg text-slate-700" x-text="`${kind}をアーカイブ`"></h2>

            <button type="button" @click="$refs.dialog.close()" aria-label="閉じる"
                    class="absolute right-4 top-4 rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>

            <p class="mt-8 break-words text-center text-sm text-slate-700">
                <span x-text="kind"></span><strong x-text="name"></strong>をアーカイブします。<br>
                <span x-show="note" x-text="note"></span><span x-show="note"><br></span>
                よろしいですか？
            </p>

            <div class="mt-10 flex justify-center gap-6">
                <button type="button" @click="$refs.dialog.close()"
                        class="w-36 rounded-lg bg-sky-50 py-2.5 text-sm font-semibold text-sky-600 transition hover:bg-sky-100">
                    キャンセル
                </button>
                <button type="button" @click="accept()"
                        class="w-36 rounded-lg bg-rose-50 py-2.5 text-sm font-semibold text-rose-500 transition hover:bg-rose-100">
                    アーカイブ
                </button>
            </div>
        </div>
    </dialog>
</div>

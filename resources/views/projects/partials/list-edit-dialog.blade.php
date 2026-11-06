{{-- リストの編集。リストメニューの「リストを編集」から開く --}}
<div x-data="{
        name: '',
        color: @js(App\Enums\ListColor::Sky->value),
        action: '',
        open(detail) {
            Object.assign(this, detail);
            this.$refs.dialog.showModal();
        },
     }"
     @open-list-edit.window="open($event.detail)">
    {{-- 余白は内側に持たせる。dialog 自体が押されたら外側（背景）を押したということ --}}
    <dialog x-ref="dialog" @click.self="$refs.dialog.close()"
            class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-2xl bg-white shadow-xl backdrop:bg-slate-900/40">
        <form autocomplete="off" method="POST" :action="action" class="relative space-y-5 p-8">
            @csrf
            @method('PUT')

            <h2 class="text-center text-lg text-slate-700">リストを編集</h2>

            <button type="button" @click="$refs.dialog.close()" aria-label="閉じる"
                    class="absolute right-4 top-4 rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>

            <div>
                <label for="list-edit-name" class="block text-sm text-slate-600">リスト名</label>
                <input autocomplete="off" id="list-edit-name" type="text" name="name" x-model="name" required maxlength="255" autofocus
                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
            </div>

            <div>
                <p class="mb-2 text-sm text-slate-600">リスト色</p>
                <x-list-color-picker x-model="color" />
            </div>

            <div class="flex justify-center gap-6 pt-3">
                <button type="button" @click="$refs.dialog.close()"
                        class="w-40 rounded-lg bg-sky-50 py-2.5 text-sm text-sky-600 transition hover:bg-sky-100">
                    キャンセル
                </button>
                <button type="submit"
                        class="w-40 rounded-lg bg-sky-500 py-2.5 text-sm text-white transition hover:bg-sky-600">
                    変更
                </button>
            </div>
        </form>
    </dialog>
</div>

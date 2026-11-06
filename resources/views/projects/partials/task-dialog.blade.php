{{--
    タスクの詳細を出す入れ物（resources/js/app.js の taskDialog）。
    中身はサーバーが返す task-detail で、data-task-form の送信ごとに差し替える。
--}}
<div x-data="taskDialog(@js($openUrl))" @open-task.window="open($event.detail.url)" @submit="submit($event)">
    <dialog x-ref="dialog" @click.self="close()" @close="closed()"
            class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-3xl rounded-2xl bg-white shadow-xl backdrop:bg-slate-900/40">
        <p x-show="error" x-cloak x-text="error" role="alert"
           class="sticky top-0 z-10 bg-rose-50 px-6 py-2 text-sm text-rose-700"></p>
        <div x-ref="body"></div>
    </dialog>
</div>

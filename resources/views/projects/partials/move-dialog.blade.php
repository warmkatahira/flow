{{-- タスクの移動先を選ぶ（resources/js/app.js の moveDialog）。メニューの「タスクを移動」から開く --}}
<div x-data="moveDialog(@js(route('projects.move-targets')), {{ $project->id }})" @open-move.window="open($event.detail)">
    <dialog x-ref="dialog" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-white p-6 shadow-xl backdrop:bg-slate-900/40">
        <form autocomplete="off" method="POST" :action="action" class="space-y-4">
            @csrf

            <div>
                <h2 class="text-base font-bold text-slate-800" >タスクを移動</h2>
                <p class="mt-1 truncate text-sm text-slate-500" x-text="name"></p>
            </div>

            <div>
                <label for="move-project" class="block text-sm text-slate-700">プロジェクト</label>
                <select id="move-project" x-model.number="projectId" @change="pickList()"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
                    <template x-for="project in projects" :key="project.id">
                        <option :value="project.id" x-text="project.name"></option>
                    </template>
                </select>
            </div>

            <div>
                <label for="move-list" class="block text-sm text-slate-700">リスト</label>
                <select id="move-list" x-model.number="targetListId" name="list_id"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
                    <template x-for="list in listOptions" :key="list.id">
                        <option :value="list.id" x-text="list.name"></option>
                    </template>
                </select>
            </div>

            <p x-show="! loading && ! canSubmit" class="text-sm text-amber-700">移動先がありません。</p>

            <div class="flex justify-end gap-2">
                <button type="button" @click="$refs.dialog.close()"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    キャンセル
                </button>
                <button type="submit" :disabled="! canSubmit"
                        class="rounded-lg bg-sky-600 px-4 py-1.5 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:opacity-50">
                    移動
                </button>
            </div>
        </form>
    </dialog>
</div>

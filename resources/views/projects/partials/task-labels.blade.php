@php
    use App\Enums\ListColor;
    use App\Models\Label;
@endphp

{{-- ラベルの欄とポップアップ（resources/js/app.js の labelPicker） --}}
<div x-data="labelPicker(@js($task->labels->pluck('id')->values()), {{ Label::MAX_PER_TASK }}, { store: @js(route('tasks.labels.store', $task)) }, @js(ListColor::Sky->value))"
     class="{{ $rowClass }}">
    <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" clip-rule="evenodd" d="M10 2c-1.716 0-3.408.106-5.07.31C3.806 2.45 3 3.414 3 4.517V17.25a.75.75 0 001.075.676L10 15.082l5.925 2.844A.75.75 0 0017 17.25V4.517c0-1.103-.806-2.068-1.93-2.207A41.403 41.403 0 0010 2z" />
    </svg>

    <button type="button" @click="open()" class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 text-left hover:text-slate-800">
        @forelse ($task->labels as $label)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2 text-xs leading-6 text-slate-700">
                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $label->color }}"></span>
                {{ $label->name }}
            </span>
        @empty
            ラベル: 指定なし
        @endforelse
    </button>

    <dialog x-ref="labels" @click.self="close()" @cancel="labelDraft = null"
            class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-xl rounded-2xl bg-white shadow-xl outline-none backdrop:bg-slate-900/20">
        {{-- 選ぶ画面 --}}
        <div x-show="view === 'pick'">
            <div class="relative px-5 pt-4 text-center">
                <h3 class="text-sm text-slate-700">ラベル</h3>
                <button type="button" @click="add()" class="absolute right-5 top-4 text-xs text-sky-600 hover:text-sky-800">＋新規ラベル追加</button>
            </div>

            {{-- 並びはドラッグで変えられる。指定なしは動かさない --}}
            <div class="grid grid-cols-2 gap-1.5 p-4 sm:grid-cols-3"
                 x-sortable="{ url: @js(route('tasks.labels.reorder', $task)), attribute: 'data-label-id' }">
                <button type="button" @click="clear()"
                        :class="selected.length === 0 ? 'bg-sky-500 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        class="rounded-lg px-3 py-2 text-center text-sm transition">
                    指定なし
                </button>

                @foreach ($task->project->labels as $label)
                    <div data-label-id="{{ $label->id }}" role="checkbox" tabindex="0"
                         :aria-checked="selected.includes({{ $label->id }}) ? 'true' : 'false'"
                         @click="toggle({{ $label->id }})" @keydown.enter.prevent="toggle({{ $label->id }})"
                         :class="selected.includes({{ $label->id }}) ? 'bg-sky-100 ring-1 ring-sky-400' : (full ? 'opacity-50' : 'hover:bg-slate-100')"
                         class="group/label flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 transition">
                        <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
                        <span class="min-w-0 flex-1 truncate">{{ $label->name }}</span>
                        <button type="button" aria-label="{{ $label->name }} を編集"
                                @click.stop="edit({ name: @js($label->name), color: @js($label->color), url: @js(route('tasks.labels.update', [$task, $label])) })"
                                class="shrink-0 rounded p-0.5 text-slate-300 transition hover:bg-white hover:text-slate-600">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>

            <form data-task-form autocomplete="off" method="POST" action="{{ route('tasks.labels.sync', $task) }}" @submit="labelDraft = null"
                  class="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                @csrf
                @method('PUT')
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="labels[]" :value="id">
                </template>
                <p class="text-xs text-sky-600">ラベル選択数 <span x-text="selected.length"></span>/{{ Label::MAX_PER_TASK }}</p>
                <button type="submit" class="rounded-lg bg-sky-50 px-6 py-1.5 text-sm text-sky-600 transition hover:bg-sky-100">保存</button>
            </form>
        </div>

        {{-- 追加・編集の画面 --}}
        <div x-show="view === 'form'" x-cloak class="p-5">
            <h3 class="text-sm text-slate-700" x-text="form.editing ? 'ラベルを編集' : 'オリジナルラベルを追加'"></h3>

            <form data-task-form autocomplete="off" method="POST" :action="form.action" class="mt-3 space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="form.method">

                <div>
                    <label for="label-name" class="block text-sm text-slate-600">ラベル名</label>
                    <input autocomplete="off" id="label-name" type="text" name="name" x-ref="labelName" x-model="form.name" required maxlength="50"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none">
                </div>

                <div>
                    <p class="mb-1 text-sm text-slate-600">カラー設定</p>
                    <x-list-color-picker x-model="form.color" />
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-sky-600">ラベル選択数 <span x-text="selected.length"></span>/{{ Label::MAX_PER_TASK }}</p>
                    <div class="flex gap-3">
                        <button type="button" @click="view = 'pick'" class="rounded-lg bg-sky-50 px-5 py-1.5 text-sm text-sky-600 transition hover:bg-sky-100">キャンセル</button>
                        <button type="submit" class="rounded-lg bg-sky-500 px-6 py-1.5 text-sm text-white transition hover:bg-sky-600" x-text="form.editing ? '変更' : '追加'"></button>
                    </div>
                </div>
            </form>

            <form data-task-form autocomplete="off" method="POST" :action="form.destroy" x-show="form.editing" class="mt-2"
                  onsubmit="return confirm('このラベルを削除します。付けているタスクからも外れます。よろしいですか？');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-rose-500 hover:text-rose-700">このラベルを削除</button>
            </form>
        </div>
    </dialog>
</div>

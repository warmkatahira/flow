{{--
    添付した PDF のプレビュー。中身（$slot）を押すと、画面の上にポップアップで開く。PDF を読み込むのは開いたときだけ
    （コメントの添付と「ファイル」タブで使う）
--}}
@props(['attachment', 'url' => null])

@php
    // システム管理者の閲覧画面では、そちらの入口の URL を渡す
    $url ??= route('attachments.show', $attachment);
    $downloadUrl = $url.(str_contains($url, '?') ? '&' : '?').'download=1';
@endphp

<div x-data="{ src: '' }" {{ $attributes }}>
    <button type="button" @click="src = @js($url); $nextTick(() => $refs.preview.showModal())"
            class="block max-w-full text-left">
        {{ $slot }}
    </button>

    <dialog x-ref="preview" @close="src = ''" @click.self="$el.close()" @keydown.escape.stop
            class="m-auto h-[90vh] w-[min(64rem,95vw)] max-w-none overflow-hidden rounded-xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/60">
        <div class="flex h-full flex-col">
            <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-2.5">
                <p class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-700">{{ $attachment->name }}</p>
                <a href="{{ $downloadUrl }}"
                   class="shrink-0 rounded-lg px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100">ダウンロード</a>
                <a href="{{ $url }}" target="_blank" rel="noopener"
                   class="shrink-0 rounded-lg px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100">別のタブで開く</a>
                <button type="button" @click="$refs.preview.close()" aria-label="プレビューを閉じる"
                        class="shrink-0 rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>
            <template x-if="src">
                <iframe :src="src" title="{{ $attachment->name }} のプレビュー" class="min-h-0 w-full flex-1 bg-slate-100"></iframe>
            </template>
        </div>
    </dialog>
</div>

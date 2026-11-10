{{-- 閲覧専用であることの案内（ボードとタスクの詳細で共通） --}}
<div class="flex items-start gap-3 rounded-lg bg-slate-800 px-4 py-2.5 text-sm text-slate-100">
    <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
        <path fill-rule="evenodd" clip-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
    <p class="min-w-0">
        <span class="font-semibold">{{ $project->personalUser?->name ?? '（不明なユーザー）' }}</span> さんの My Project を閲覧しています。
        <span class="text-slate-300">閲覧専用です。操作はできず、見ても本人への通知や既読などアプリ内の記録は残りません。</span>
    </p>
</div>

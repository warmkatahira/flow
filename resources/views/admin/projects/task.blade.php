{{-- タスクの詳細（システム管理者の閲覧用）。何も書き込まない --}}
<x-layouts.app :title="'#'.$task->number.' '.$task->title">
    <div class="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        @include('admin.projects.partials.breadcrumb', ['items' => [
            [$user->name, route('admin.projects.user', $user)],
            [$project->name, route('admin.projects.show', [$user, $project])],
            ['#'.$task->number, null],
        ]])

        @include('admin.projects.partials.notice')

        <article class="rounded-xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="font-medium text-slate-400">#{{ $task->number }}</span>
                <span class="inline-flex items-center gap-1 {{ $task->status->textClass() }}">
                    <x-task-status-icon :status="$task->status" class="h-4 w-4" />
                    {{ $task->status->label() }}
                </span>
                <span>・ {{ $task->list?->name }}</span>
                @if ($task->isArchived())
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700">アーカイブ中</span>
                @endif
            </div>

            <h1 class="mt-2 break-words text-xl font-bold text-slate-800">{{ $task->title }}</h1>

            <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[auto_1fr]">
                <dt class="text-slate-500">期間</dt>
                <dd class="text-slate-700">
                    @if ($task->started_on || $task->ended_on)
                        {{ $task->started_on?->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }} 〜 {{ $task->ended_on?->locale('ja')->isoFormat('YYYY/MM/DD(ddd)') }}
                    @else
                        <span class="text-slate-400">なし</span>
                    @endif
                </dd>

                <dt class="text-slate-500">担当者</dt>
                <dd class="text-slate-700">
                    {{ $task->assignees->pluck('name')->join('、') ?: '' }}
                    @if ($task->assignees->isEmpty())
                        <span class="text-slate-400">なし</span>
                    @endif
                </dd>

                <dt class="text-slate-500">タグ</dt>
                <dd class="flex flex-wrap gap-1">
                    @forelse ($task->labels as $label)
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs text-slate-700" style="background-color: {{ $label->color }}1f">
                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $label->color }}"></span>
                            {{ $label->name }}
                        </span>
                    @empty
                        <span class="text-slate-400">なし</span>
                    @endforelse
                </dd>
            </dl>

            <section class="mt-6">
                <h2 class="text-sm font-bold text-slate-700">説明</h2>
                @if ($task->description)
                    <div class="rich-text mt-2 break-words text-sm text-slate-700">{{ $task->descriptionHtml() }}</div>
                @else
                    <p class="mt-2 text-sm text-slate-400">説明はありません。</p>
                @endif
            </section>

            @foreach ($task->checklists as $checklist)
                <section class="mt-6">
                    <h2 class="text-sm font-bold text-slate-700">
                        {{ $checklist->title }}
                        <span class="ml-1 font-normal text-slate-400">{{ $checklist->progress() }}%</span>
                    </h2>
                    <ul class="mt-2 space-y-1">
                        @forelse ($checklist->items as $item)
                            <li class="flex items-start gap-2 text-sm">
                                <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border {{ $item->is_done ? 'border-sky-500 bg-sky-500 text-white' : 'border-slate-300' }}" aria-hidden="true">
                                    @if ($item->is_done)
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" /></svg>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1 break-words {{ $item->is_done ? 'text-slate-400 line-through' : 'text-slate-700' }}">{{ $item->title }}</span>
                                @if ($item->periodLabel())
                                    <span class="shrink-0 whitespace-nowrap text-xs text-slate-500">{{ $item->periodLabel() }}</span>
                                @endif
                                @if ($item->assignee)
                                    <span class="shrink-0 text-xs text-slate-500">{{ $item->assignee->name }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-slate-400">項目はありません。</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach

            <section class="mt-6">
                <h2 class="text-sm font-bold text-slate-700">コメント <span class="ml-1 font-normal text-slate-400">{{ $task->comments->count() }}</span></h2>
                <div class="mt-2 space-y-3">
                    @forelse ($task->comments as $comment)
                        <div class="flex gap-3">
                            <x-user-avatar :user="$comment->user" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-slate-500">
                                    <span class="font-medium text-slate-700">{{ $comment->user?->name }}</span>
                                    {{ $comment->created_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd) HH:mm') }}
                                    @if ($comment->isEdited())
                                        （編集済み {{ $comment->updated_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd) HH:mm') }}）
                                    @endif
                                </p>
                                @if ($comment->body)
                                    <div class="rich-text mt-1 break-words text-sm text-slate-700">{{ $comment->bodyHtml() }}</div>
                                @endif
                                {{-- 添付ファイル。画像は小さく出し、PDF は押すとプレビュー、それ以外はダウンロード --}}
                                @if ($comment->attachments->isNotEmpty())
                                    <ul class="mt-1.5 flex flex-wrap items-start gap-1.5">
                                        @foreach ($comment->attachments as $attachment)
                                            @php
                                                $url = route('admin.projects.attachment', $attachment);
                                            @endphp
                                            <li class="max-w-full">
                                                @if ($attachment->isInlineImage())
                                                    <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ $attachment->name }}（{{ $attachment->humanSize() }}）"
                                                       class="block overflow-hidden rounded-md ring-1 ring-slate-200">
                                                        <img src="{{ $url }}" alt="{{ $attachment->name }}" loading="lazy" class="h-24 max-w-48 object-cover">
                                                    </a>
                                                @elseif ($attachment->isPdf())
                                                    <x-pdf-preview :attachment="$attachment" :url="$url">
                                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs text-sky-700 hover:bg-slate-200">
                                                            <x-paperclip-icon class="h-3.5 w-3.5 shrink-0" />
                                                            <span class="truncate">{{ $attachment->name }}</span>
                                                            <span class="shrink-0 text-slate-400">{{ $attachment->humanSize() }}</span>
                                                        </span>
                                                    </x-pdf-preview>
                                                @else
                                                    <a href="{{ $url }}?download=1" download="{{ $attachment->name }}"
                                                       class="inline-flex max-w-full items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs text-sky-700 hover:bg-slate-200">
                                                        <x-paperclip-icon class="h-3.5 w-3.5 shrink-0" />
                                                        <span class="truncate">{{ $attachment->name }}</span>
                                                        <span class="shrink-0 text-slate-400">{{ $attachment->humanSize() }}</span>
                                                    </a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">コメントはありません。</p>
                    @endforelse
                </div>
            </section>

            <p class="mt-6 border-t border-slate-100 pt-3 text-xs text-slate-400">
                作成 {{ $task->created_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd) HH:mm') }} {{ $task->creator?->name }}
                ・ 最終更新 {{ $task->updated_at->locale('ja')->isoFormat('YYYY/MM/DD(ddd) HH:mm') }} {{ $task->updater?->name }}
            </p>
        </article>
    </div>
</x-layouts.app>

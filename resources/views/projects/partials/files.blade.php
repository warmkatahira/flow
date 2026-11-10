{{--
    プロジェクトのファイル（コメントに添付されたもの）の一覧。新しい順
    他の人の変更で差し替えるので、絞り込みの文字（fileQuery）は外側に持つ
    タスク名を押すと詳細を開く（ボードに出ていないタスクと、アーカイブ中のプロジェクトでは開けない）
--}}
@if ($files->isEmpty())
    <div class="rounded-xl bg-white px-6 py-12 text-center shadow-sm">
        <x-paperclip-icon class="mx-auto h-8 w-8 text-slate-300" />
        <p class="mt-2 text-sm text-slate-500">ファイルはまだありません。</p>
        <p class="mt-1 text-xs text-slate-400">タスクのコメントにファイルを添付すると、ここに並びます。</p>
    </div>
@else
    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <table class="w-full table-fixed text-sm">
            <thead class="border-b border-slate-100 bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">ファイル名</th>
                    <th class="hidden w-1/5 px-4 py-2.5 font-semibold md:table-cell">タスク</th>
                    <th class="hidden w-40 px-4 py-2.5 font-semibold lg:table-cell">投稿者</th>
                    <th class="hidden w-36 px-4 py-2.5 font-semibold sm:table-cell">日時</th>
                    <th class="hidden w-28 px-4 py-2.5 text-right font-semibold sm:table-cell">大きさ</th>
                    <th class="w-40 px-4 py-2.5"><span class="sr-only">操作</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($files as $file)
                    @php
                        $task = $file->comment->task;
                        $openable = ! $project->isArchived() && $task->isOnBoard();
                        $extension = strtoupper(pathinfo($file->name, PATHINFO_EXTENSION));
                    @endphp
                    <tr x-show="@js(mb_strtolower($file->name)).includes(fileQuery.trim().toLowerCase())" class="align-middle transition hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($file->isInlineImage())
                                    <img src="{{ route('attachments.show', $file) }}" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-md object-cover ring-1 ring-slate-200">
                                @else
                                    <span @class([
                                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-[10px] font-bold',
                                        'bg-rose-500 text-white' => $file->isPdf(),
                                        'bg-slate-100 text-slate-500' => ! $file->isPdf(),
                                    ])>{{ mb_substr($extension ?: 'FILE', 0, 4) }}</span>
                                @endif
                                @if ($file->isPdf())
                                    {{-- PDF はファイル名を押すとプレビューする --}}
                                    <x-pdf-preview :attachment="$file" class="min-w-0">
                                        <span class="block truncate text-sky-700 hover:underline" title="{{ $file->name }}（押すとプレビュー）">{{ $file->name }}</span>
                                    </x-pdf-preview>
                                @else
                                    <span class="min-w-0 truncate text-slate-800" title="{{ $file->name }}">{{ $file->name }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="hidden px-4 py-2.5 md:table-cell">
                            @if ($openable)
                                <button type="button" @click="$dispatch('open-task', { url: @js(route('tasks.show', $task)) })"
                                        class="block w-full truncate text-left text-sky-700 hover:underline" title="{{ $task->title }}">
                                    <span class="text-xs text-slate-400">#{{ $task->number }}</span> {{ $task->title }}
                                </button>
                            @else
                                <span class="block truncate text-slate-500" title="{{ $task->title }}">
                                    <span class="text-xs text-slate-400">#{{ $task->number }}</span> {{ $task->title }}
                                </span>
                                @unless ($task->isOnBoard())
                                    <span class="text-[11px] text-amber-600">アーカイブ中</span>
                                @endunless
                            @endif
                        </td>
                        <td class="hidden px-4 py-2.5 lg:table-cell">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-user-avatar :user="$file->comment->user" size="xxs" />
                                <span class="truncate text-slate-600">{{ $file->comment->user->name }}</span>
                            </div>
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-2.5 text-slate-500 sm:table-cell">{{ $file->created_at->format('Y/m/d H:i') }}</td>
                        <td class="hidden whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-500 sm:table-cell">{{ $file->humanSize() }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('attachments.show', [$file, 'download' => 1]) }}" download="{{ $file->name }}"
                                   class="inline-flex items-center gap-1 whitespace-nowrap rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 transition hover:bg-sky-100">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" />
                                        <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                                    </svg>
                                    ダウンロード
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

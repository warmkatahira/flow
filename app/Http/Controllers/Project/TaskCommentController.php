<?php

namespace App\Http\Controllers\Project;

use App\Enums\Reaction;
use App\Enums\TaskActivity;
use App\Events\NotificationReceived;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Rules\RichTextLength;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskCommentController extends Controller
{
    use RendersTaskDetail;

    public function store(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        // ファイルだけを添付するなら、本文はなくてもよい
        $hasFiles = $request->hasFile('attachments');

        $attributes = $request->validate([
            'body' => [$hasFiles ? 'nullable' : 'required', 'string', 'max:100000', new RichTextLength(required: ! $hasFiles)],
            'attachments' => ['array', 'max:'.TaskCommentAttachment::MAX_FILES],
            'attachments.*' => ['file', 'max:'.TaskCommentAttachment::MAX_KILOBYTES],
        ], [
            'attachments.max' => 'ファイルは1つのコメントに'.TaskCommentAttachment::MAX_FILES.'つまで添付できます。',
            'attachments.*.max' => '添付できるファイルは1つ'.(TaskCommentAttachment::MAX_KILOBYTES / 1024).'MBまでです。',
            'attachments.*.uploaded' => '添付できるファイルは1つ'.(TaskCommentAttachment::MAX_KILOBYTES / 1024).'MBまでです。',
        ]);

        $comment = $task->comments()->make(['body' => $attributes['body'] ?? '']);
        $comment->user_id = $request->user()->getKey();
        $comment->save();
        $comment->syncMentions($task->project->users);

        foreach ($request->file('attachments', []) as $file) {
            TaskCommentAttachment::storeFor($comment, $file);
        }

        $mentioned = $comment->mentions()->pluck('users.id')->reject(fn (int $id) => $id === $comment->user_id);

        foreach ($mentioned as $userId) {
            NotificationReceived::notify($userId);
        }

        // メンションした人にはメンションとして届いているので、同じコメントを二重に知らせない
        // 本文のないコメントは、添付したファイル名を出す
        $summary = trim($comment->bodyText()) ?: '📎 '.$comment->attachments()->pluck('name')->join('、');

        $task->recordActivity($request->user(), TaskActivity::Comment, $summary, $mentioned->all());

        return $this->changed($task);
    }

    /**
     * 消せるのは投稿した本人だけ
     */
    public function destroy(Request $request, TaskComment $comment): View
    {
        $this->authorizeDetail($comment->task);

        abort_unless($comment->user_id === $request->user()->getKey(), 403);

        $comment->delete();

        return $this->changed($comment->task);
    }

    /**
     * 1つのコメントに1人1つ。同じ絵文字を押すと外し、別の絵文字を押すと付け替える
     */
    public function react(Request $request, TaskComment $comment): View
    {
        $this->authorizeDetail($comment->task);

        $emoji = Reaction::from($request->validate([
            'emoji' => ['required', Rule::enum(Reaction::class)],
        ])['emoji']);

        $mine = $comment->reactions()->firstWhere('user_id', $request->user()->getKey());

        if ($mine?->emoji === $emoji) {
            $mine->delete();
        } else {
            $reaction = $mine ?? $comment->reactions()->make();
            $reaction->user_id = $request->user()->getKey();
            $reaction->emoji = $emoji;
            $reaction->save();
        }

        return $this->changed($comment->task);
    }
}

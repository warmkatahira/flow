<?php

namespace App\Http\Controllers\Project;

use App\Enums\Reaction;
use App\Events\MentionReceived;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use App\Models\TaskComment;
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

        $attributes = $request->validate([
            'body' => ['required', 'string', 'max:100000', new RichTextLength(required: true)],
        ]);

        $comment = $task->comments()->make($attributes);
        $comment->user_id = $request->user()->getKey();
        $comment->save();
        $comment->syncMentions($task->project->users);

        foreach ($comment->mentions()->pluck('users.id')->reject(fn (int $id) => $id === $comment->user_id) as $userId) {
            MentionReceived::notify($userId);
        }

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

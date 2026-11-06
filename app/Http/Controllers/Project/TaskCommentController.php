<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskCommentController extends Controller
{
    use RendersTaskDetail;

    public function store(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $comment = $task->comments()->make($attributes);
        $comment->user_id = $request->user()->getKey();
        $comment->save();
        $comment->syncMentions($task->project->users);

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
}

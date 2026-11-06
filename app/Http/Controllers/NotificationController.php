<?php

namespace App\Http\Controllers;

use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 通知（自分へのメンション）
 */
class NotificationController extends Controller
{
    /**
     * 未読の件数と一覧を返す
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'count' => $user->unreadMentionCount(),
            'html' => view('components.notification-list', [
                'comments' => $user->mentionedComments()
                    ->with(['user', 'task.project'])
                    ->orderByPivot('created_at', 'desc')
                    ->limit(20)
                    ->get(),
            ])->render(),
        ]);
    }

    /**
     * そのタスクを開く（開いたときに TaskDetailController が既読にする）
     */
    public function open(Request $request, TaskComment $comment): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->mentionedComments()->whereKey($comment->getKey())->exists(), 404);

        return redirect()->route('projects.show', [$comment->task->project_id, 'task' => $comment->task_id]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->markMentionsRead();

        return $this->index($request);
    }
}

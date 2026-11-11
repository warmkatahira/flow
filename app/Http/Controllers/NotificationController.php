<?php

namespace App\Http\Controllers;

use App\Models\TaskComment;
use App\Models\TaskNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 通知（自分へのメンションと、フォローしたタスクの動き）
 *
 * どちらも1つのベルにまとめ、新しい順に混ぜて出す。
 */
class NotificationController extends Controller
{
    private const LIMIT = 20;

    /**
     * 未読の件数と一覧を返す
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $mentions = $user->mentionedComments()
            ->with(['user', 'task.project'])
            ->orderByPivot('created_at', 'desc')
            ->limit(self::LIMIT)
            ->get();

        $activities = $user->taskNotifications()
            ->with(['actor', 'task.project'])
            ->latest()
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        $notifications = $mentions->toBase()
            ->merge($activities)
            ->sortByDesc(fn ($item) => $item instanceof TaskComment ? $item->pivot->created_at : $item->created_at)
            ->take(self::LIMIT)
            ->values();

        return response()->json([
            'count' => $user->unreadNotificationCount(),
            'unread' => $user->unreadMarks(),
            'html' => view('components.notification-list', ['notifications' => $notifications])->render(),
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

    /**
     * フォローしたタスクの通知から開く
     *
     * アーカイブされたタスクは開けないので、この通知だけ既読にしてプロジェクトのアーカイブを開く。
     */
    public function openActivity(Request $request, TaskNotification $notification): RedirectResponse
    {
        abort_unless($request->user()->taskNotifications()->whereKey($notification->getKey())->exists(), 404);

        $task = $notification->task;

        if (! $task->isOnBoard()) {
            $notification->read_at = now();
            $notification->save();

            return redirect()
                ->route('projects.show', [$task->project_id, 'tab' => 'archive'])
                ->with('status', "#{$task->number} {$task->title} はアーカイブされています");
        }

        return redirect()->route('projects.show', [$task->project_id, 'task' => $task->getKey()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->markNotificationsRead();

        return $this->index($request);
    }
}

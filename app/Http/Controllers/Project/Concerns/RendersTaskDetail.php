<?php

namespace App\Http\Controllers\Project\Concerns;

use App\Events\BoardChanged;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * タスクの詳細画面。操作のたびに中身を返し、画面はそれで差し替える
 */
trait RendersTaskDetail
{
    protected function authorizeDetail(Task $task): void
    {
        Gate::authorize('work', $task->project);

        abort_unless($task->isOnBoard(), 404);
    }

    protected function detail(Task $task): View
    {
        return view('projects.partials.task-detail', [
            'task' => $task->fresh(['list', 'project.users', 'project.labels', 'labels', 'assignees', 'checklists.items.assignee', 'comments.user', 'comments.mentions', 'comments.reactions.user', 'creator']),
        ]);
    }

    /**
     * カードの表示も変わるので、ボードを開いている人へ知らせてから中身を返す
     */
    protected function changed(Task $task): View
    {
        BoardChanged::notify($task->project);

        return $this->detail($task);
    }
}

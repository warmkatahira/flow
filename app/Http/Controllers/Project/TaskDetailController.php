<?php

namespace App\Http\Controllers\Project;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskDetailController extends Controller
{
    use RendersTaskDetail;

    /**
     * 開いたら、このタスクの自分宛ての通知は見たことになる
     */
    public function show(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $request->user()->markMentionsRead($task);

        return $this->detail($task);
    }

    /**
     * タイトルと説明を変える（どちらか片方だけでもよい）
     */
    public function update(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $task->update($request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]));

        return $this->changed($task);
    }

    public function status(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $task->update($request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]));

        return $this->changed($task);
    }

    /**
     * 期間を変える。開始日だけ・終了日だけでもよい
     */
    public function period(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $task->update($request->validate([
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', ...($request->filled('started_on') ? ['after_or_equal:started_on'] : [])],
        ]));

        return $this->changed($task);
    }

    /**
     * 担当者を付け外しする（プロジェクトの参加者だけ）
     */
    public function assignee(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = $task->project->users()->whereKey($attributes['user_id'])->firstOrFail();

        $task->assignees()->toggle($user->getKey());

        return $this->changed($task);
    }
}

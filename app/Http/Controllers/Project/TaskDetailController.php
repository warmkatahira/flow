<?php

namespace App\Http\Controllers\Project;

use App\Enums\TaskActivity;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use App\Rules\RichTextLength;
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

        $request->user()->markNotificationsRead($task);

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
            'description' => ['sometimes', 'nullable', 'string', 'max:100000', new RichTextLength],
        ]));

        if ($task->wasChanged('title')) {
            $task->recordActivity($request->user(), TaskActivity::Title);
        }

        if ($task->wasChanged('description')) {
            $task->recordActivity($request->user(), TaskActivity::Description);
        }

        return $this->changed($task);
    }

    public function status(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $task->update($request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]));

        if ($task->wasChanged('status')) {
            $task->recordActivity($request->user(), TaskActivity::Status, $task->status->label());
        }

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

        if ($task->wasChanged(['started_on', 'ended_on'])) {
            $period = collect([$task->started_on, $task->ended_on])->filter()->map->format('n/j')->unique()->join('〜');

            $task->recordActivity($request->user(), TaskActivity::Period, $period ?: null);
        }

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

        $task->recordActivity($request->user(), TaskActivity::Assignee);

        return $this->changed($task);
    }

    /**
     * 自分のフォローを付け外しする。自分の画面のことなので、他の人へは知らせない
     */
    public function follow(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $task->followers()->toggle($request->user()->getKey());

        return $this->detail($task);
    }
}

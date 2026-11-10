<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\View\View;

/**
 * 全員の My Project の閲覧（システム管理者のみ）
 *
 * 見るだけの入口。ProjectPolicy を通さないので、ここから先の操作（タスクの更新・コメント・
 * お気に入りなど）は今まで通りすべて 404 になる。
 *
 * 見たことが本人に伝わったり履歴に残ったりしないよう、ここでは何も書き込まない
 * （通知の既読・最終更新者・フォローなどを変えない。リアルタイム更新のチャンネルにも入らない）。
 */
class PersonalProjectController extends Controller
{
    /**
     * ユーザーごとの My Project の一覧
     */
    public function index(): View
    {
        return view('admin.my-projects.index', [
            // 削除済みのユーザーの My Project も残っているので出す
            'users' => User::withTrashed()
                ->with(['personalProject' => fn ($query) => $query->withCount([
                    'tasks' => fn ($tasks) => $tasks->active()->whereHas('list', fn ($lists) => $lists->active()),
                ])->withMax('tasks', 'updated_at')])
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * My Project のボード（読み取り専用）
     */
    public function show(Project $project): View
    {
        abort_unless($project->isPersonal(), 404);

        return view('admin.my-projects.show', [
            'project' => $project->load([
                'personalUser',
                'taskLists' => fn ($query) => $query->active(),
                'taskLists.tasks' => fn ($query) => $query->active(),
                'taskLists.tasks.assignees',
                'taskLists.tasks.labels',
            ]),
        ]);
    }

    /**
     * My Project のタスクの詳細（読み取り専用）
     */
    public function task(Project $project, Task $task): View
    {
        abort_unless($project->isPersonal() && $task->project_id === $project->id, 404);

        return view('admin.my-projects.task', [
            'project' => $project->load('personalUser'),
            'task' => $task->load([
                'list',
                'assignees',
                'labels',
                'creator',
                'updater',
                'checklists.items.assignee',
                'comments.user',
                'comments.attachments',
                'comments.mentions',
            ]),
        ]);
    }
}

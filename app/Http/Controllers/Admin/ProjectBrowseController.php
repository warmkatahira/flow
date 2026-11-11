<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * プロジェクト管理（システム管理者のみ）。全員のプロジェクトを見るだけの入口
 *
 * ユーザー → そのユーザーが参加しているプロジェクト → 中身（ボード・ファイル）→ タスクの詳細 と辿る。
 * ProjectPolicy を通さないので、ふつうの画面や操作の URL は今まで通りシステム管理者にも 404 のまま。
 *
 * 見たことが本人に伝わったり履歴に残ったりしないよう、ここでは何も書き込まない
 * （通知の既読・最終更新者・フォローなどを変えない。リアルタイム更新のチャンネルにも入らない）。
 */
class ProjectBrowseController extends Controller
{
    /**
     * ユーザーの一覧
     */
    public function index(): View
    {
        return view('admin.projects.index', [
            // 削除済みのユーザーの参加もプロジェクトも残っているので出す
            'users' => User::withTrashed()->withCount('projects')->orderBy('id')->get(),
        ]);
    }

    /**
     * そのユーザーが参加しているプロジェクトの一覧（My Project が先頭、アーカイブ済みは後ろ）
     */
    public function user(User $user): View
    {
        $projects = $user->projects()
            ->withCount([
                'users',
                'tasks' => fn ($tasks) => $tasks->active()->whereHas('list', fn ($lists) => $lists->active()),
            ])
            ->withMax('tasks', 'updated_at')
            ->orderByRaw('personal_user_id is null')
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();

        return view('admin.projects.user', [
            'user' => $user,
            'projects' => $projects,
        ]);
    }

    /**
     * プロジェクトの中身（ボードとファイル。読み取り専用）
     */
    public function show(User $user, Project $project): View
    {
        $this->ensureParticipant($user, $project);

        return view('admin.projects.show', [
            'user' => $user,
            'project' => $project->load([
                'taskLists' => fn ($query) => $query->active(),
                'taskLists.tasks' => fn ($query) => $query->active(),
                'taskLists.tasks.assignees',
                'taskLists.tasks.labels',
            ]),
            'files' => TaskCommentAttachment::query()
                ->whereHas('comment.task', fn ($tasks) => $tasks->where('project_id', $project->getKey()))
                ->with(['comment.user', 'comment.task.list'])
                ->latest()
                ->latest('id')
                ->get(),
        ]);
    }

    /**
     * タスクの詳細（読み取り専用）
     */
    public function task(User $user, Project $project, Task $task): View
    {
        $this->ensureParticipant($user, $project);
        abort_unless($task->project_id === $project->id, 404);

        return view('admin.projects.task', [
            'user' => $user,
            'project' => $project,
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

    /**
     * 添付ファイル（画像と PDF はブラウザで開く。?download=1 ならダウンロード）
     */
    public function attachment(Request $request, TaskCommentAttachment $attachment): StreamedResponse
    {
        return $attachment->toDownloadResponse($request->boolean('download'));
    }

    /**
     * URL のユーザーがそのプロジェクトに参加しているか（辿ってきた道筋と食い違う URL は見せない）
     */
    private function ensureParticipant(User $user, Project $project): void
    {
        abort_unless($project->users()->withTrashed()->whereKey($user->getKey())->exists(), 404);
    }
}

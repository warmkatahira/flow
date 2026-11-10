<?php

namespace App\Http\Controllers\Project;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * プロジェクト管理
 *
 * 参加していないプロジェクトは 404 になる（ProjectPolicy 参照）。
 */
class ProjectController extends Controller
{
    /**
     * リストとタスクの移動先の候補（自分が操作できるプロジェクトと、そのリスト）
     */
    public function moveTargets(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()
            ->active()
            ->with(['taskLists' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        return response()->json($projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'lists' => $project->taskLists->map->only('id', 'name')->values(),
        ]));
    }

    /**
     * プロジェクトの詳細を表示する
     */
    public function show(Project $project): View
    {
        Gate::authorize('view', $project);

        return view('projects.show', $this->boardData($project));
    }

    /**
     * ボードとアーカイブの最新の中身を返す（他の人の変更を画面へ反映するときに取り直す）
     */
    public function board(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $data = $this->boardData($project);
        $board = $project->isArchived() ? 'projects.partials.board-readonly' : 'projects.partials.board';

        return response()->json([
            'board' => view($board, $data)->render(),
            'archive' => view('projects.partials.archive', $data)->render(),
            'archiveCount' => $data['archivedLists']->count() + $data['archivedTasks']->count(),
            'filterLabels' => view('projects.partials.filter-labels', $data)->render(),
            'calendar' => $data['calendarTasks'],
            'files' => view('projects.partials.files', $data)->render(),
        ]);
    }

    /**
     * プロジェクトの画面に渡す内容
     *
     * @return array<string, mixed>
     */
    private function boardData(Project $project): array
    {
        $data = [
            'project' => $project->load([
                'users' => fn ($query) => $query->orderBy('users.id'),
                'taskLists' => fn ($query) => $query->active(),
                'taskLists.tasks' => fn ($query) => $query->active()->withCount([
                    'checklistItems',
                    'checklistItems as done_checklist_items_count' => fn ($items) => $items->where('is_done', true),
                ]),
                'taskLists.tasks.assignees',
                'taskLists.tasks.labels',
                'labels',
            ]),
            'archivedLists' => $project->taskLists()
                ->archived()
                ->withCount(['tasks' => fn ($query) => $query->active()])
                ->reorder('archived_at', 'desc')
                ->get(),
            // リストごとアーカイブされたタスクは、リストを戻せば一緒に戻るのでここには出さない
            'archivedTasks' => $project->tasks()
                ->archived()
                ->whereHas('list', fn ($query) => $query->active())
                ->with('list')
                ->latest('archived_at')
                ->get(),
        ];

        // コメントに添付されたファイル（「ファイル」タブ）。アーカイブ中のタスクのものも出す
        $data['files'] = TaskCommentAttachment::query()
            ->whereHas('comment.task', fn ($tasks) => $tasks->where('project_id', $project->getKey()))
            ->with(['comment.user', 'comment.task.list'])
            ->latest()
            ->latest('id')
            ->get();

        // 自分がフォローしているタスク（カードの印と「フォロー中」の絞り込みに使う）
        $data['followedTaskIds'] = auth()->user()->followedTasks()->where('tasks.project_id', $project->getKey())->pluck('tasks.id');

        return [...$data, 'calendarTasks' => $this->calendarTasks($data['project'], $data['followedTaskIds'])];
    }

    /**
     * カレンダーに出すタスク（開始日か終了日のあるもの）。読み込み済みのボードから作る
     *
     * 開始日だけ・終了日だけのものは、その1日に出す。
     *
     * @param  SupportCollection<int, int>  $followedTaskIds
     * @return list<array<string, mixed>>
     */
    private function calendarTasks(Project $project, SupportCollection $followedTaskIds): array
    {
        $tasks = [];

        foreach ($project->taskLists as $list) {
            foreach ($list->tasks as $task) {
                if (! $task->started_on && ! $task->ended_on) {
                    continue;
                }

                $start = $task->started_on ?? $task->ended_on;
                $end = $task->ended_on ?? $task->started_on;

                $tasks[] = [
                    'id' => $task->id,
                    'number' => $task->number,
                    'title' => $task->title,
                    'start' => $start->min($end)->format('Y-m-d'),
                    'end' => $end->max($start)->format('Y-m-d'),
                    'status' => $task->status->value,
                    'closed' => in_array($task->status, [TaskStatus::Done, TaskStatus::Canceled], true),
                    'due' => $task->ended_on?->format('Y-m-d'),
                    'list' => $list->name,
                    'color' => $list->color,
                    'assignees' => $task->assignees->modelKeys(),
                    'labels' => $task->labels->modelKeys(),
                    'followed' => $followedTaskIds->contains($task->id),
                    'url' => $project->isArchived() ? null : route('tasks.show', $task),
                ];
            }
        }

        return $tasks;
    }

    /**
     * プロジェクト作成画面を表示する
     */
    public function create(): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.create', [
            'users' => $this->selectableUsers(),
            'roles' => ProjectRole::options(),
        ]);
    }

    /**
     * プロジェクトを作成する
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create([
            'name' => $request->validated('name'),
            'icon_path' => $this->resolveIconPath($request),
        ]);

        // 作った人は必ずオーナーになる
        $project->users()->sync($this->participants($request, $request->user()));
        $project->createDefaultLabels();
        $project->createDefaultTaskLists();

        return redirect()
            ->route('projects.show', $project)
            ->with('toast', ['message' => 'プロジェクトを作成しました']);
    }

    /**
     * プロジェクトの設定画面（名前・アイコン・参加者・アーカイブ）を表示する
     */
    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.edit', [
            'project' => $project->load('users'),
            // タブに出す件数（プロジェクトの画面のアーカイブタブと同じ数え方）
            'archiveCount' => $project->taskLists()->archived()->count()
                + $project->tasks()->archived()->whereHas('list', fn ($query) => $query->active())->count(),
            'users' => $this->selectableUsers(),
            'roles' => ProjectRole::options(),
        ]);
    }

    /**
     * プロジェクトを更新する
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update([
            'name' => $request->validated('name'),
            'icon_path' => $this->resolveIconPath($request, $project),
        ]);

        // 削除済みのユーザーは画面に出ないので、そのまま同期すると参加が外れて復元しても戻らない
        $deleted = $project->users()->onlyTrashed()->get()
            ->mapWithKeys(fn (User $user) => [$user->getKey() => ['role' => $user->pivot->role]])
            ->all();

        $project->users()->sync($this->participants($request) + $deleted);

        // 参加者から外れた人は担当とフォローからも外す（チェックリストの項目の担当者も）
        $participants = $project->users()->withTrashed()->pluck('users.id');

        foreach (['task_user', 'task_follows'] as $table) {
            DB::table($table)
                ->whereIn('task_id', $project->tasks()->select('id'))
                ->whereNotIn('user_id', $participants)
                ->delete();
        }

        TaskChecklistItem::query()
            ->whereIn('task_checklist_id', TaskChecklist::query()->whereIn('task_id', $project->tasks()->select('id'))->select('id'))
            ->whereNotIn('assignee_id', $participants)
            ->update(['assignee_id' => null]);

        BoardChanged::notify($project, reload: true);

        // 自分をオーナーから外した場合など、更新後に見えなくなることがある
        if (! $project->fresh()->hasParticipant($request->user())) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'プロジェクトを更新しました。自分を参加者から外したため一覧には表示されません。');
        }

        // 続けて直せるよう、設定画面のままにする
        return redirect()
            ->route('projects.edit', $project)
            ->with('status', 'プロジェクトを更新しました。');
    }

    /**
     * 自分が参加しているアーカイブ済みプロジェクトの一覧を表示する
     */
    public function archived(Request $request): View
    {
        return view('projects.archived', [
            'projects' => $request->user()->projects()->archived()->latest('archived_at')->get(),
        ]);
    }

    /**
     * プロジェクトをアーカイブする
     *
     * 消さないので、参加者・お気に入り・グループの割り当てやアイコン画像はそのまま残る。
     */
    public function archive(Project $project): RedirectResponse
    {
        Gate::authorize('archive', $project);

        $project->archive();

        BoardChanged::notify($project, reload: true);

        return redirect()
            ->route('dashboard')
            ->with('toast', ['message' => 'アーカイブしました', 'url' => route('projects.unarchive', $project)]);
    }

    /**
     * アーカイブしたプロジェクトを元に戻す
     */
    public function unarchive(Project $project): RedirectResponse
    {
        Gate::authorize('archive', $project);

        $project->unarchive();

        BoardChanged::notify($project, reload: true);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'プロジェクトを元に戻しました。');
    }

    /**
     * アーカイブ済みのプロジェクトを完全に削除する
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        if ($project->icon_path) {
            Storage::disk('public')->delete($project->icon_path);
        }

        // 参加者・リスト・タスクは外部キーの cascade で一緒に消える。コメントの添付ファイルの中身は先に消す
        foreach ($project->tasks()->pluck('id') as $taskId) {
            Storage::disk(TaskCommentAttachment::DISK)->deleteDirectory(TaskCommentAttachment::directoryFor($taskId));
        }

        $project->delete();

        BoardChanged::notify($project, reload: true);

        return redirect()
            ->route('projects.archived')
            ->with('toast', ['message' => '削除しました']);
    }

    /**
     * 参加者として選べるユーザー（無効なユーザーは選べない）
     *
     * @return Collection<int, User>
     */
    private function selectableUsers(): Collection
    {
        return User::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * sync() に渡す [user_id => ['role' => ...]] を組み立てる
     *
     * @return array<int, array<string, string>>
     */
    private function participants(FormRequest $request, ?User $forceOwner = null): array
    {
        $roles = $request->input('roles', []);

        $participants = [];

        foreach ($request->input('members', []) as $id) {
            $role = ProjectRole::tryFrom($roles[$id] ?? '') ?? ProjectRole::Member;

            $participants[(int) $id] = ['role' => $role->value];
        }

        if ($forceOwner) {
            $participants[$forceOwner->getKey()] = ['role' => ProjectRole::Owner->value];
        }

        return $participants;
    }

    /**
     * アイコン画像を保存して、保存後のパスを返す
     *
     * 差し替え・削除のときは古いファイルを消す。
     */
    private function resolveIconPath(FormRequest $request, ?Project $project = null): ?string
    {
        if ($request->boolean('remove_icon')) {
            if ($project?->icon_path) {
                Storage::disk('public')->delete($project->icon_path);
            }

            return null;
        }

        if ($icon = $request->file('icon')) {
            if ($project?->icon_path) {
                Storage::disk('public')->delete($project->icon_path);
            }

            return $icon->store('project-icons', 'public');
        }

        return $project?->icon_path;
    }
}

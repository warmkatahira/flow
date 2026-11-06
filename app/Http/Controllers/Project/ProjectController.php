<?php

namespace App\Http\Controllers\Project;

use App\Enums\ProjectRole;
use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    /**
     * プロジェクトの画面に渡す内容
     *
     * @return array<string, mixed>
     */
    private function boardData(Project $project): array
    {
        return [
            'project' => $project->load([
                'users' => fn ($query) => $query->orderBy('users.id'),
                'taskLists' => fn ($query) => $query->active(),
                'taskLists.tasks' => fn ($query) => $query->active(),
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

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'プロジェクトを作成しました。');
    }

    /**
     * プロジェクト編集画面を表示する
     */
    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.edit', [
            'project' => $project->load('users'),
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

        BoardChanged::notify($project, reload: true);

        // 自分をオーナーから外した場合など、更新後に見えなくなることがある
        if (! $project->fresh()->hasParticipant($request->user())) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'プロジェクトを更新しました。自分を参加者から外したため一覧には表示されません。');
        }

        return redirect()
            ->route('projects.show', $project)
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
            ->with('status', 'プロジェクトをアーカイブしました。');
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

        // 参加者・リスト・タスクは外部キーの cascade で一緒に消える
        $project->delete();

        BoardChanged::notify($project, reload: true);

        return redirect()
            ->route('projects.archived')
            ->with('status', 'プロジェクトを完全に削除しました。');
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

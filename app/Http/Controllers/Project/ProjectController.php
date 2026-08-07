<?php

namespace App\Http\Controllers\Project;

use App\Enums\ListColor;
use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
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

        return view('projects.show', [
            'project' => $project->load([
                'users' => fn ($query) => $query->orderBy('users.id'),
                'taskLists.tasks',
            ]),
            'colors' => ListColor::cases(),
        ]);
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
            'groups' => $this->selectableGroups(),
        ]);
    }

    /**
     * プロジェクトを作成する
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create([
            'name' => $request->validated('name'),
            'group_id' => $request->validated('group_id'),
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
            'groups' => $this->selectableGroups(),
        ]);
    }

    /**
     * プロジェクトを更新する
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update([
            'name' => $request->validated('name'),
            'group_id' => $request->validated('group_id'),
            'icon_path' => $this->resolveIconPath($request, $project),
        ]);

        $project->users()->sync($this->participants($request));

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
     * プロジェクトを削除する
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        if ($project->icon_path) {
            Storage::disk('public')->delete($project->icon_path);
        }

        // 参加者（project_user）は外部キーの cascade で一緒に消える
        $project->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'プロジェクトを削除しました。');
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
     * グループのプルダウン用 [id => 名前]
     *
     * @return array<int, string>
     */
    private function selectableGroups(): array
    {
        return ProjectGroup::orderBy('name')->pluck('name', 'id')->all();
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

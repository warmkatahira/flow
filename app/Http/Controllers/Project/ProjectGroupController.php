<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectGroupRequest;
use App\Http\Requests\Project\UpdateProjectGroupRequest;
use App\Models\ProjectGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * プロジェクトをまとめるグループの管理
 *
 * グループは名前だけの入れ物で、中に何が入っているかは見せない
 * （所属プロジェクトの秘匿性は ProjectPolicy 側で担保している）。
 */
class ProjectGroupController extends Controller
{
    /**
     * グループ一覧を表示する
     */
    public function index(): View
    {
        return view('project-groups.index', [
            'groups' => ProjectGroup::orderBy('name')->get(),
        ]);
    }

    /**
     * グループ作成画面を表示する
     */
    public function create(): View
    {
        return view('project-groups.create');
    }

    /**
     * グループを作成する
     */
    public function store(StoreProjectGroupRequest $request): RedirectResponse
    {
        ProjectGroup::create($request->validated());

        return redirect()
            ->route('project-groups.index')
            ->with('status', 'グループを作成しました。');
    }

    /**
     * グループ編集画面を表示する
     */
    public function edit(ProjectGroup $projectGroup): View
    {
        return view('project-groups.edit', ['group' => $projectGroup]);
    }

    /**
     * グループを更新する
     */
    public function update(UpdateProjectGroupRequest $request, ProjectGroup $projectGroup): RedirectResponse
    {
        $projectGroup->update($request->validated());

        return redirect()
            ->route('project-groups.index')
            ->with('status', 'グループを更新しました。');
    }

    /**
     * グループを削除する
     *
     * 所属していたプロジェクトは消さず、「グループなし」に戻す（外部キーの nullOnDelete）。
     */
    public function destroy(ProjectGroup $projectGroup): RedirectResponse
    {
        $projectGroup->delete();

        return redirect()
            ->route('project-groups.index')
            ->with('status', 'グループを削除しました。所属していたプロジェクトはグループなしになりました。');
    }
}

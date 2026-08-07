<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectGroupRequest;
use App\Http\Requests\Project\UpdateProjectGroupRequest;
use App\Models\ProjectGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            'groups' => ProjectGroup::ordered()->get(),
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
        $group = ProjectGroup::make($request->validated());
        $group->position = (int) ProjectGroup::max('position') + 1;
        $group->save();

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

    /**
     * 並び順を保存する（ドラッグ＆ドロップから呼ばれる）
     */
    public function reorder(Request $request): Response
    {
        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $groups = ProjectGroup::whereKey($attributes['ids'])->get()->keyBy('id');

        DB::transaction(function () use ($attributes, $groups) {
            foreach ($attributes['ids'] as $position => $id) {
                // position は fillable に載せていないので明示的に入れる
                $group = $groups->get($id);

                if ($group) {
                    $group->position = $position;
                    $group->save();
                }
            }
        });

        return response()->noContent();
    }
}

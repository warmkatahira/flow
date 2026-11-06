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
 * グループは個人のもの。作った人にしか見えず、他人のグループは触れない
 * （他人の id を指定しても 404 になる）。
 */
class ProjectGroupController extends Controller
{
    /**
     * 自分のグループ一覧を表示する
     */
    public function index(Request $request): View
    {
        return view('project-groups.index', [
            'groups' => $request->user()->projectGroups()->ordered()->get(),
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
        $group = $request->user()->projectGroups()->make($request->validated());
        $group->position = (int) $request->user()->projectGroups()->max('position') + 1;
        $group->save();

        return redirect()
            ->route('project-groups.index')
            ->with('status', 'グループを作成しました。');
    }

    /**
     * グループ編集画面を表示する
     */
    public function edit(Request $request, ProjectGroup $projectGroup): View
    {
        $this->ensureOwned($request, $projectGroup);

        return view('project-groups.edit', ['group' => $projectGroup]);
    }

    /**
     * グループを更新する
     */
    public function update(UpdateProjectGroupRequest $request, ProjectGroup $projectGroup): RedirectResponse
    {
        $this->ensureOwned($request, $projectGroup);

        $projectGroup->update($request->validated());

        return redirect()
            ->route('project-groups.index')
            ->with('status', 'グループを更新しました。');
    }

    /**
     * グループを削除する
     *
     * 入れていたプロジェクトは消さず、「グループなし」に戻す（外部キーの nullOnDelete）。
     */
    public function destroy(Request $request, ProjectGroup $projectGroup): RedirectResponse
    {
        $this->ensureOwned($request, $projectGroup);

        $projectGroup->delete();

        return redirect()
            ->route('project-groups.index')
            ->with('toast', ['message' => '削除しました']);
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

        // 自分のグループだけを対象にする
        $groups = $request->user()->projectGroups()->whereKey($attributes['ids'])->get()->keyBy('id');

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

    /**
     * 他人のグループは存在ごと隠す
     */
    private function ensureOwned(Request $request, ProjectGroup $group): void
    {
        abort_unless($group->user_id === $request->user()->getKey(), 404);
    }
}

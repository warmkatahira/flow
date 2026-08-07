<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * ダッシュボード（自分が参加しているプロジェクトの一覧）
     *
     * グループは個人のもの。どのプロジェクトをどのグループに入れているかは
     * project_user（人とプロジェクトの組）に入っているので、
     * 自分のリレーション越しに取ってくる。
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $projects = $user->projects()
            ->with(['users' => fn ($query) => $query->orderBy('users.id')])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $groups = $user->projectGroups()->ordered()->get();

        $byGroupId = $projects->groupBy(fn (Project $project) => (string) ($project->pivot->group_id ?? ''))->toBase();

        // セクションはグループ側を起点に組み立てる。
        // プロジェクトを集約して作ると、まだ何も入れていないグループが消えてしまう。
        // 並びはグループ管理で決めた順（position）で、中はプロジェクト名順のまま。
        $sections = $groups->mapWithKeys(
            fn ($group) => [$group->name => $byGroupId->get((string) $group->id, collect())],
        )->toBase();

        // 未所属は最後に「グループなし」として置く（1件もなければ出さない）
        $ungrouped = $byGroupId->get('', collect());

        if ($ungrouped->isNotEmpty()) {
            $sections->put('', $ungrouped);
        }

        return view('dashboard', [
            'projects' => $projects,
            'sections' => $sections,
            'groups' => $groups,
            // 星の初期状態。以降の付け外しは画面側の store が持つ
            'favoriteIds' => $projects
                ->filter(fn (Project $project) => (bool) $project->pivot->is_favorite)
                ->pluck('id')
                ->values(),
        ]);
    }
}

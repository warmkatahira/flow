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
        $groupsById = $groups->keyBy('id');

        // グループごとにまとめる。未所属は最後に「グループなし」として置く。
        // 並びはグループ管理で決めた順（position）。sortBy は安定なので、
        // 同じグループの中はプロジェクト名順のまま残る。
        $grouped = $projects
            ->sortBy(fn (Project $project) => $groupsById->get($project->pivot->group_id)?->position ?? PHP_INT_MAX)
            ->groupBy(fn (Project $project) => $groupsById->get($project->pivot->group_id)?->name ?? '')
            ->toBase();

        // ここで名前順に並べ直すと position を上書きしてしまうので、順番は触らない
        $ungrouped = $grouped->pull('');
        $sections = $grouped;

        if ($ungrouped) {
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

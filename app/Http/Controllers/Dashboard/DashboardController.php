<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * ダッシュボード（自分が参加しているプロジェクトの一覧。アーカイブ済みは出さない）
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $projects = $user->projects()
            ->active()
            ->with(['users' => fn ($query) => $query->orderBy('users.id')])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        // My Project はお気に入りやグループに混ぜず、いちばん上に固定で出す
        [$personal, $projects] = $projects->partition(fn (Project $project) => $project->isPersonal());
        $projects = $projects->values();

        $groups = $user->projectGroups()->ordered()->get();
        $sections = $user->projectSections($projects, $groups);

        // ドラッグで「グループなし」へ戻せるよう、グループがあるときは空でも受け皿を置いておく
        // （空のあいだは画面側でドラッグ中だけ出す）
        if ($groups->isNotEmpty() && ! $sections->has('')) {
            $sections->put('', collect());
        }

        return view('dashboard', [
            'personalProject' => $personal->first(),
            'projects' => $projects,
            'sections' => $sections,
            'groups' => $groups,
            'groupIds' => $groups->pluck('id', 'name'),
            // カードの ⋯ のチェックの初期状態。以降は画面側の store が持つ
            'assignedGroups' => $projects->mapWithKeys(fn (Project $project) => [$project->id => $project->pivot->group_id]),
            // 星の初期状態。以降の付け外しは画面側の store が持つ
            'favoriteIds' => $projects
                ->filter(fn (Project $project) => (bool) $project->pivot->is_favorite)
                ->pluck('id')
                ->values(),
        ]);
    }
}

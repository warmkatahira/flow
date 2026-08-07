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
     */
    public function index(Request $request): View
    {
        $projects = Project::visibleTo($request->user())
            ->with(['group', 'users' => fn ($query) => $query->orderBy('users.id')])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        // グループごとにまとめる。未所属は最後に「グループなし」として置く。
        // groupBy の戻りは Eloquent Collection なので、キー操作のために toBase() しておく
        $grouped = $projects->groupBy(fn (Project $project) => $project->group?->name ?? '')->toBase();
        $ungrouped = $grouped->pull('');
        $sections = $grouped->sortKeys();

        if ($ungrouped) {
            $sections->put('', $ungrouped);
        }

        return view('dashboard', [
            'projects' => $projects,
            'sections' => $sections,
            // 星の初期状態。以降の付け外しは画面側の store が持つ
            'favoriteIds' => $projects
                ->filter(fn (Project $project) => $project->isFavoritedBy($request->user()))
                ->pluck('id')
                ->values(),
        ]);
    }
}

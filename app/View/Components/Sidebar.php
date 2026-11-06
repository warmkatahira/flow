<?php

namespace App\View\Components;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * 左のプロジェクトリスト（お気に入りと、自分のグループごとの一覧）
 */
class Sidebar extends Component
{
    public function __construct(private Request $request) {}

    public function render(): View
    {
        $user = $this->request->user();

        $projects = $user->projects()->orderBy('name')->get();
        $active = $projects->reject(fn (Project $project) => $project->isArchived())->values();

        $current = $this->request->route('project');

        return view('components.sidebar', [
            'projects' => $active,
            'sections' => $user->projectSections($active, $user->projectGroups()->ordered()->get()),
            // 星の初期状態。アーカイブ済みのプロジェクトの画面にも星があるので、それらも含める
            'favoriteIds' => $projects
                ->filter(fn (Project $project) => (bool) $project->pivot->is_favorite)
                ->pluck('id')
                ->values(),
            'currentId' => $current instanceof Project ? $current->id : null,
        ]);
    }
}

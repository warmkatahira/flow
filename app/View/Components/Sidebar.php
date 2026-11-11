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

        // My Project はお気に入りやグループに混ぜず、いちばん上に固定で出す
        [$personal, $active] = $active->partition(fn (Project $project) => $project->isPersonal());
        $active = $active->values();

        $current = $this->request->route('project');

        return view('components.sidebar', [
            'personalProject' => $personal->first(),
            'projects' => $active,
            'sections' => $user->projectSections($active, $user->projectGroups()->ordered()->get()),
            // 星の初期状態。アーカイブ済みのプロジェクトの画面にも星があるので、それらも含める
            'favoriteIds' => $projects
                ->filter(fn (Project $project) => (bool) $project->pivot->is_favorite)
                ->pluck('id')
                ->values(),
            'currentId' => $current instanceof Project ? $current->id : null,
            // 未読の印の最初の状態。以降はベルが取り直す $store.unread で切り替える
            'unreadProjectIds' => $user->unreadMarks()['projects'],
        ]);
    }
}

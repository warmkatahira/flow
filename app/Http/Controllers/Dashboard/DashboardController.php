<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
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

        $groups = $user->projectGroups()->ordered()->get();

        return view('dashboard', [
            'projects' => $projects,
            'sections' => $user->projectSections($projects, $groups),
            'groups' => $groups,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * プロジェクトを自分のグループに入れる／外す
 *
 * グループは個人のものなので、変えるのは自分のダッシュボードでの分類だけ。
 * 他の参加者の見え方には影響しない。
 */
class ProjectGroupAssignmentController extends Controller
{
    public function __invoke(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        $user = $request->user();

        $attributes = $request->validate([
            'group_id' => ['nullable', 'integer'],
        ]);

        // 自分のグループでなければ「グループなし」に倒す
        $groupId = $attributes['group_id'] ?? null;

        if ($groupId !== null && ! $user->projectGroups()->whereKey($groupId)->exists()) {
            $groupId = null;
        }

        $project->users()->updateExistingPivot($user, ['group_id' => $groupId]);

        return back();
    }
}

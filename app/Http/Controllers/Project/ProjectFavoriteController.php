<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * プロジェクトのお気に入り
 *
 * いいねと同じで、同じ操作で付け外しする。
 * 参加していないプロジェクトは view の認可で 404 になる。
 */
class ProjectFavoriteController extends Controller
{
    public function __invoke(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $project);

        $user = $request->user();
        $isFavorite = ! $project->isFavoritedBy($user);

        $project->users()->updateExistingPivot($user, ['is_favorite' => $isFavorite]);

        if ($request->expectsJson()) {
            return response()->json(['is_favorite' => $isFavorite]);
        }

        return back();
    }
}

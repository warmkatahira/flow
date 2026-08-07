<?php

namespace App\Http\Controllers\Project;

use App\Enums\ListColor;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * カンバンのリスト（列）
 *
 * ボードの操作は参加者なら誰でもできる（オーナー限定はプロジェクトの設定側だけ）。
 */
class TaskListController extends Controller
{
    /**
     * リストを追加する
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', Rule::enum(ListColor::class)],
        ]);

        $project->taskLists()->create([
            'name' => $attributes['name'],
            'color' => $attributes['color'] ?? ListColor::Sky->value,
            'position' => (int) $project->taskLists()->max('position') + 1,
        ]);

        return back();
    }

    /**
     * リストの名前と色を変える
     */
    public function update(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('view', $list->project);

        $list->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', Rule::enum(ListColor::class)],
        ]));

        return back();
    }

    /**
     * リストを削除する（中のタスクも一緒に消える）
     */
    public function destroy(TaskList $list): RedirectResponse
    {
        Gate::authorize('view', $list->project);

        $list->delete();

        return back()->with('status', 'リストを削除しました。');
    }

    /**
     * リストの並び順を保存する
     */
    public function reorder(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他プロジェクトの id を混ぜられても動かないよう、自分のリストだけを対象にする
        $lists = $project->taskLists()->whereKey($attributes['ids'])->get()->keyBy('id');

        foreach ($attributes['ids'] as $position => $id) {
            $lists->get($id)?->update(['position' => $position]);
        }

        return back();
    }
}

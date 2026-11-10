<?php

namespace App\Http\Controllers\Project;

use App\Enums\ListColor;
use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('work', $project);

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'regex:'.ListColor::PATTERN],
        ]);

        $project->taskLists()->create([
            'name' => $attributes['name'],
            'color' => $attributes['color'] ?? ListColor::Sky->value,
            'position' => (int) $project->taskLists()->max('position') + 1,
        ]);

        BoardChanged::notify($project);

        return back();
    }

    /**
     * リストの名前と色を変える
     */
    public function update(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('work', $list->project);

        abort_if($list->isArchived(), 404);

        $list->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:'.ListColor::PATTERN],
        ]));

        BoardChanged::notify($list->project);

        return back();
    }

    /**
     * リストを中のタスクごと複製して、すぐ右に置く（アーカイブ済みのタスクは複製しない）
     */
    public function duplicate(Request $request, TaskList $list): RedirectResponse
    {
        $project = $list->project;

        Gate::authorize('work', $project);

        abort_if($list->isArchived(), 404);

        DB::transaction(function () use ($request, $project, $list) {
            $locked = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $project->taskLists()->where('position', '>', $list->position)->increment('position');

            $copy = $list->replicate();
            $copy->name = mb_substr($list->name, 0, 250).' のコピー';
            $copy->position = $list->position + 1;
            $copy->save();

            $number = $locked->nextTaskNumber();

            foreach ($list->tasks()->active()->get() as $task) {
                $taskCopy = $task->replicate();
                $taskCopy->task_list_id = $copy->getKey();
                $taskCopy->number = $number++;
                $taskCopy->created_by = $request->user()->getKey();
                $taskCopy->updated_by = $request->user()->getKey();
                $taskCopy->save();

                $task->copyDetailsTo($taskCopy);
            }
        });

        BoardChanged::notify($project);

        return back()->with('toast', ['message' => '複製しました']);
    }

    /**
     * リストをアーカイブする（中のタスクも一緒にボードから消える）
     */
    public function archive(TaskList $list): RedirectResponse
    {
        Gate::authorize('work', $list->project);

        abort_if($list->isArchived(), 404);

        $list->archive();

        BoardChanged::notify($list->project);

        return back()->with('toast', ['message' => 'アーカイブしました', 'url' => route('lists.unarchive', $list)]);
    }

    /**
     * アーカイブしたリストをボードの末尾に戻す（中のタスクも一緒に戻る）
     *
     * アーカイブした直後の取り消し（undo）は元の位置に戻す。
     */
    public function unarchive(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('work', $list->project);

        abort_unless($list->isArchived(), 404);

        $undo = $request->boolean('undo');

        if (! $undo) {
            $list->position = (int) $list->project->taskLists()->max('position') + 1;
        }

        $list->unarchive();

        BoardChanged::notify($list->project);

        return $undo ? back() : back()->with('status', "{$list->name} を元に戻しました。");
    }

    /**
     * アーカイブ済みのリストを完全に削除する（中のタスクも一緒に消える）
     */
    public function destroy(TaskList $list): RedirectResponse
    {
        Gate::authorize('work', $list->project);

        abort_unless($list->isArchived(), 404);

        $list->delete();

        BoardChanged::notify($list->project);

        return back()->with('toast', ['message' => '削除しました']);
    }

    /**
     * リストの並び順を保存する
     */
    public function reorder(Request $request, Project $project): Response
    {
        Gate::authorize('work', $project);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他プロジェクトの id を混ぜられても動かないよう、ボードに出ているリストだけを対象にする
        $lists = $project->taskLists()->active()->whereKey($attributes['ids'])->get()->keyBy('id');

        foreach ($attributes['ids'] as $position => $id) {
            $lists->get($id)?->update(['position' => $position]);
        }

        BoardChanged::notify($project);

        return response()->noContent();
    }
}

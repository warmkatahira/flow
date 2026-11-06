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
    public function duplicate(TaskList $list): RedirectResponse
    {
        $project = $list->project;

        Gate::authorize('work', $project);

        abort_if($list->isArchived(), 404);

        $copy = DB::transaction(function () use ($project, $list) {
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
                $taskCopy->save();
            }

            return $copy;
        });

        BoardChanged::notify($project);

        return back()->with('status', "{$copy->name} を作成しました。");
    }

    /**
     * リストを中のタスクごと別のプロジェクトの末尾へ動かす
     *
     * アーカイブ済みのタスクも一緒に動かす。番号はプロジェクトごとの連番なので振り直す。
     */
    public function move(Request $request, TaskList $list): RedirectResponse
    {
        $from = $list->project;

        Gate::authorize('work', $from);

        abort_if($list->isArchived(), 404);

        $attributes = $request->validate([
            'project_id' => ['required', 'integer', Rule::notIn([$from->getKey()])],
        ]);

        $to = Project::findOrFail($attributes['project_id']);

        Gate::authorize('work', $to);

        DB::transaction(function () use ($list, $from, $to) {
            // 順番を揃えて、同時に逆向きへ動かしてもデッドロックしないようにする
            Project::whereKey([$from->getKey(), $to->getKey()])->orderBy('id')->lockForUpdate()->get();

            $number = $to->nextTaskNumber();

            foreach ($list->tasks()->reorder('number')->get() as $task) {
                $task->project_id = $to->getKey();
                $task->number = $number++;
                $task->save();
            }

            $list->project_id = $to->getKey();
            $list->position = (int) $to->taskLists()->max('position') + 1;
            $list->save();
        });

        BoardChanged::notify($from);
        BoardChanged::notify($to);

        return back()->with('status', "{$list->name} を {$to->name} へ移動しました。");
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

        return back()->with('status', "{$list->name} をアーカイブしました。");
    }

    /**
     * アーカイブしたリストをボードの末尾に戻す（中のタスクも一緒に戻る）
     */
    public function unarchive(TaskList $list): RedirectResponse
    {
        Gate::authorize('work', $list->project);

        abort_unless($list->isArchived(), 404);

        $list->position = (int) $list->project->taskLists()->max('position') + 1;
        $list->unarchive();

        BoardChanged::notify($list->project);

        return back()->with('status', "{$list->name} を元に戻しました。");
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

        return back()->with('status', "{$list->name} を完全に削除しました。");
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

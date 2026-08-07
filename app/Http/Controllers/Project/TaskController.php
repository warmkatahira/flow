<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * タスクを追加する
     */
    public function store(Request $request, TaskList $list): RedirectResponse
    {
        $project = $list->project;

        Gate::authorize('view', $project);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        // 番号がぶつからないよう、プロジェクトの行をロックしてから採番する
        DB::transaction(function () use ($project, $list, $attributes) {
            $locked = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $task = $list->tasks()->make([
                'title' => $attributes['title'],
                'position' => (int) $list->tasks()->max('position') + 1,
            ]);

            // 採番と所属はユーザー入力から入り込まないよう、fillable には載せずここで入れる
            $task->project_id = $locked->getKey();
            $task->number = $locked->nextTaskNumber();
            $task->save();
        });

        return back();
    }

    /**
     * タスクを更新する
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task->project);

        $task->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]));

        return back();
    }

    /**
     * タスクを削除する
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('view', $task->project);

        $task->delete();

        return back()->with('status', 'タスクを削除しました。');
    }

    /**
     * ドラッグ＆ドロップの結果を保存する
     *
     * 動かしたタスクの移動先リストと、そのリストの新しい並び順をまとめて受け取る。
     */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $attributes = $request->validate([
            'list_id' => ['required', 'integer'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他プロジェクトのリストやタスクを混ぜられても動かないようにする
        $list = $project->taskLists()->whereKey($attributes['list_id'])->firstOrFail();
        $tasks = $project->tasks()->whereKey($attributes['ids'])->get()->keyBy('id');

        DB::transaction(function () use ($attributes, $list, $tasks) {
            foreach ($attributes['ids'] as $position => $id) {
                $tasks->get($id)?->update([
                    'task_list_id' => $list->getKey(),
                    'position' => $position,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }
}

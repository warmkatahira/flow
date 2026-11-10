<?php

namespace App\Http\Controllers\Project;

use App\Enums\TaskActivity;
use App\Events\BoardChanged;
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

        Gate::authorize('work', $project);

        abort_if($list->isArchived(), 404);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        // 番号がぶつからないよう、プロジェクトの行をロックしてから採番する
        DB::transaction(function () use ($request, $project, $list, $attributes) {
            $locked = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $task = $list->tasks()->make([
                'title' => $attributes['title'],
                'position' => (int) $list->tasks()->max('position') + 1,
            ]);

            // 採番と所属はユーザー入力から入り込まないよう、fillable には載せずここで入れる
            $task->project_id = $locked->getKey();
            $task->number = $locked->nextTaskNumber();
            $task->created_by = $request->user()->getKey();
            $task->save();
        });

        BoardChanged::notify($project);

        return back();
    }

    /**
     * タスクを複製してすぐ下に置く
     */
    public function duplicate(Request $request, Task $task): RedirectResponse
    {
        $project = $task->project;

        Gate::authorize('work', $project);

        abort_unless($task->isOnBoard(), 404);

        DB::transaction(function () use ($request, $project, $task) {
            $locked = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $task->list->tasks()->where('position', '>', $task->position)->increment('position');

            $copy = $task->replicate();
            $copy->title = mb_substr($task->title, 0, 250).' のコピー';
            $copy->number = $locked->nextTaskNumber();
            $copy->position = $task->position + 1;
            $copy->created_by = $request->user()->getKey();
            $copy->save();

            $task->copyDetailsTo($copy);
        });

        BoardChanged::notify($project);

        return back()->with('toast', ['message' => '複製しました']);
    }

    /**
     * タスクを別のリストの末尾へ動かす（他のプロジェクトへも動かせる）
     *
     * 番号はプロジェクトごとの連番なので、プロジェクトをまたぐと振り直す。
     */
    public function move(Request $request, Task $task): RedirectResponse
    {
        $from = $task->project;

        Gate::authorize('work', $from);

        abort_unless($task->isOnBoard(), 404);

        $attributes = $request->validate([
            'list_id' => ['required', 'integer'],
        ]);

        $list = TaskList::active()->findOrFail($attributes['list_id']);
        $to = $list->project;

        Gate::authorize('work', $to);

        $movedList = $task->task_list_id !== $list->getKey();

        DB::transaction(function () use ($task, $list, $from, $to) {
            // 順番を揃えて、同時に逆向きへ動かしてもデッドロックしないようにする
            Project::whereKey([$from->getKey(), $to->getKey()])->orderBy('id')->lockForUpdate()->get();

            if ($from->isNot($to)) {
                $task->project_id = $to->getKey();
                $task->number = $to->nextTaskNumber();
                $task->keepAssigneesIn($to);
                // ラベルの種類はプロジェクトごとなので持っていけない
                $task->labels()->detach();
            }

            $task->task_list_id = $list->getKey();
            $task->position = (int) $list->tasks()->max('position') + 1;
            $task->save();
        });

        if ($movedList) {
            $task->notifyFollowers($request->user(), TaskActivity::Moved, $from->isNot($to) ? "{$to->name} / {$list->name}" : $list->name);
        }

        BoardChanged::notify($from);

        if ($from->isNot($to)) {
            BoardChanged::notify($to);
        }

        return back()->with('status', "{$task->title} を {$list->name} へ移動しました。");
    }

    /**
     * タスクをアーカイブする
     */
    public function archive(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('work', $task->project);

        abort_unless($task->isOnBoard(), 404);

        $task->archive();
        $task->notifyFollowers($request->user(), TaskActivity::Archived);

        BoardChanged::notify($task->project);

        return back()->with('toast', ['message' => 'アーカイブしました', 'url' => route('tasks.unarchive', $task)]);
    }

    /**
     * 選んだタスクをまとめてアーカイブする
     *
     * 画面は読み込み直すので、結果はフラッシュメッセージで伝える。
     */
    public function archiveMany(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('work', $project);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他プロジェクトや、ボードに出ていないものを混ぜられても動かないようにする
        $ids = $project->tasks()
            ->active()
            ->whereKey($attributes['ids'])
            ->whereHas('list', fn ($query) => $query->active())
            ->pluck('id');

        $count = $project->tasks()->whereKey($ids)->update(['archived_at' => now()]);

        foreach ($project->tasks()->whereKey($ids)->get() as $task) {
            $task->notifyFollowers($request->user(), TaskActivity::Archived);
        }

        BoardChanged::notify($project);

        session()->flash('toast', [
            'message' => "{$count}件のタスクをアーカイブしました",
            'url' => route('tasks.unarchive-many', $project),
            'ids' => $ids->all(),
        ]);

        return response()->json(['archived' => $count]);
    }

    /**
     * まとめてアーカイブしたタスクを取り消す（元の位置に戻す）
     */
    public function unarchiveMany(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('work', $project);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $tasks = $project->tasks()
            ->archived()
            ->whereKey($attributes['ids'])
            ->whereHas('list', fn ($query) => $query->active())
            ->get();

        $project->tasks()->whereKey($tasks->modelKeys())->update(['archived_at' => null]);

        foreach ($tasks as $task) {
            $task->notifyFollowers($request->user(), TaskActivity::Restored);
        }

        BoardChanged::notify($project);

        return back();
    }

    /**
     * アーカイブしたタスクを元のリストの末尾に戻す
     *
     * アーカイブした直後の取り消し（undo）は元の位置に戻す。
     */
    public function unarchive(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('work', $task->project);

        abort_unless($task->isArchived(), 404);

        if ($task->list->isArchived()) {
            return back()->with('status', '先にリストを元に戻してください。');
        }

        $undo = $request->boolean('undo');

        if (! $undo) {
            $task->position = (int) $task->list->tasks()->max('position') + 1;
        }

        $task->unarchive();
        $task->notifyFollowers($request->user(), TaskActivity::Restored);

        BoardChanged::notify($task->project);

        return $undo ? back() : back()->with('status', "#{$task->number} を元に戻しました。");
    }

    /**
     * アーカイブ済みのタスクを完全に削除する
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('work', $task->project);

        abort_unless($task->isArchived(), 404);

        $task->delete();

        BoardChanged::notify($task->project);

        return back()->with('toast', ['message' => '削除しました']);
    }

    /**
     * ドラッグ＆ドロップの結果を保存する
     *
     * リストの新しい並び順（ids）と、そのうち本人が動かしたタスク（moved）を受け取る。
     * 所属を変えるのは moved だけ。ids は送った人の画面の状態なので、他の人が別のリストへ
     * 動かしたばかりのタスクが混ざっていることがあり、それを元へ戻してはいけない。
     */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('work', $project);

        $attributes = $request->validate([
            'list_id' => ['required', 'integer'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'moved' => ['array'],
            'moved.*' => ['integer'],
        ]);

        // 他プロジェクトやアーカイブ済みのものを混ぜられても動かないようにする
        $list = $project->taskLists()->active()->whereKey($attributes['list_id'])->firstOrFail();
        $tasks = $project->tasks()->active()->whereKey($attributes['ids'])->get()->keyBy('id');

        $movedTasks = collect();

        DB::transaction(function () use ($attributes, $list, $tasks, $movedTasks) {
            foreach ($attributes['ids'] as $position => $id) {
                $task = $tasks->get($id);

                if ($task === null) {
                    continue;
                }

                if (in_array($id, $attributes['moved'] ?? [], true)) {
                    if ($task->task_list_id !== $list->getKey()) {
                        $movedTasks->push($task);
                    }

                    $task->task_list_id = $list->getKey();
                } elseif ($task->task_list_id !== $list->getKey()) {
                    continue;
                }

                $task->position = $position;
                $task->save();
            }
        });

        // 同じリストの中の並べ替えは知らせない
        foreach ($movedTasks as $task) {
            $task->notifyFollowers($request->user(), TaskActivity::Moved, $list->name);
        }

        BoardChanged::notify($project);

        return response()->json(['ok' => true]);
    }
}

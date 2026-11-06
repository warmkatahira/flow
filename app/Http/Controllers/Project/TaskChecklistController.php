<?php

namespace App\Http\Controllers\Project;

use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TaskChecklistController extends Controller
{
    use RendersTaskDetail;

    public function store(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $checklist = $task->checklists()->make($attributes);
        $checklist->position = (int) $task->checklists()->max('position') + 1;
        $checklist->save();

        return $this->changed($task);
    }

    public function update(Request $request, TaskChecklist $checklist): View
    {
        $this->authorizeDetail($checklist->task);

        $checklist->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]));

        return $this->changed($checklist->task);
    }

    public function destroy(TaskChecklist $checklist): View
    {
        $this->authorizeDetail($checklist->task);

        $checklist->delete();

        return $this->changed($checklist->task);
    }

    /**
     * チェックリストの並び順を保存する
     */
    public function reorder(Request $request, Task $task): Response
    {
        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他のタスクの id を混ぜられても動かないよう、このタスクのものだけを対象にする
        $checklists = $task->checklists()->whereKey($attributes['ids'])->get()->keyBy('id');

        foreach ($attributes['ids'] as $position => $id) {
            $checklist = $checklists->get($id);

            if ($checklist) {
                $checklist->position = $position;
                $checklist->save();
            }
        }

        BoardChanged::notify($task->project);

        return response()->noContent();
    }

    /**
     * 項目の並び順を保存する（同じタスクのチェックリストをまたいで動かせる）
     *
     * 所属を変えるのは moved だけ。ids は送った人の画面の状態なので、他の人が別の枠へ
     * 動かしたばかりの項目が混ざっていることがあり、それを元へ戻してはいけない。
     */
    public function reorderItems(Request $request, TaskChecklist $checklist): Response
    {
        $task = $checklist->task;

        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'moved' => ['nullable', 'integer'],
        ]);

        $items = TaskChecklistItem::query()
            ->whereIn('task_checklist_id', $task->checklists()->select('id'))
            ->whereKey($attributes['ids'])
            ->get()
            ->keyBy('id');

        foreach ($attributes['ids'] as $position => $id) {
            $item = $items->get($id);

            if ($item === null) {
                continue;
            }

            if ($id === ($attributes['moved'] ?? null)) {
                $item->task_checklist_id = $checklist->getKey();
            } elseif ($item->task_checklist_id !== $checklist->getKey()) {
                continue;
            }

            $item->position = $position;
            $item->save();
        }

        BoardChanged::notify($task->project);

        return response()->noContent();
    }

    public function storeItem(Request $request, TaskChecklist $checklist): View
    {
        $this->authorizeDetail($checklist->task);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $item = $checklist->items()->make($attributes);
        $item->position = (int) $checklist->items()->max('position') + 1;
        $item->save();

        return $this->changed($checklist->task);
    }

    /**
     * チェックの付け外し
     */
    public function toggleItem(TaskChecklistItem $item): View
    {
        $task = $item->checklist->task;

        $this->authorizeDetail($task);

        $item->update(['is_done' => ! $item->is_done]);

        return $this->changed($task);
    }

    public function destroyItem(TaskChecklistItem $item): View
    {
        $task = $item->checklist->task;

        $this->authorizeDetail($task);

        $item->delete();

        return $this->changed($task);
    }
}

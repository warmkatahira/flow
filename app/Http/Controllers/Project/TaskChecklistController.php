<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use Illuminate\Http\Request;
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

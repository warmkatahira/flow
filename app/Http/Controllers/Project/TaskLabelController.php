<?php

namespace App\Http\Controllers\Project;

use App\Enums\ListColor;
use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Project\Concerns\RendersTaskDetail;
use App\Models\Label;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ラベル。種類の管理もタスクの詳細画面から行うので、どれもタスクを通して受ける
 */
class TaskLabelController extends Controller
{
    use RendersTaskDetail;

    /**
     * タスクに付けるラベルを保存する
     */
    public function sync(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'labels' => ['array', 'max:'.Label::MAX_PER_TASK],
            'labels.*' => ['integer', Rule::exists('labels', 'id')->where('project_id', $task->project_id)],
        ]);

        $task->labels()->sync($attributes['labels'] ?? []);

        return $this->changed($task);
    }

    public function store(Request $request, Task $task): View
    {
        $this->authorizeDetail($task);

        $label = $task->project->labels()->make($this->validated($request, $task));
        $label->position = (int) $task->project->labels()->max('position') + 1;
        $label->save();

        return $this->changed($task);
    }

    public function update(Request $request, Task $task, Label $label): View
    {
        $this->authorizeLabel($task, $label);

        $label->update($this->validated($request, $task, $label));

        return $this->changed($task);
    }

    public function destroy(Task $task, Label $label): View
    {
        $this->authorizeLabel($task, $label);

        $label->delete();

        return $this->changed($task);
    }

    /**
     * ラベルの並び順を保存する
     */
    public function reorder(Request $request, Task $task): Response
    {
        $this->authorizeDetail($task);

        $attributes = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他のプロジェクトの id を混ぜられても動かないよう、このプロジェクトのものだけを対象にする
        $labels = $task->project->labels()->whereKey($attributes['ids'])->get()->keyBy('id');

        foreach ($attributes['ids'] as $position => $id) {
            $label = $labels->get($id);

            if ($label) {
                $label->position = $position;
                $label->save();
            }
        }

        BoardChanged::notify($task->project);

        return response()->noContent();
    }

    private function authorizeLabel(Task $task, Label $label): void
    {
        $this->authorizeDetail($task);

        abort_unless($label->project_id === $task->project_id, 404);
    }

    /**
     * @return array<string, string>
     */
    private function validated(Request $request, Task $task, ?Label $label = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('labels')->where('project_id', $task->project_id)->ignore($label),
            ],
            'color' => ['required', 'regex:'.ListColor::PATTERN],
        ]);
    }
}

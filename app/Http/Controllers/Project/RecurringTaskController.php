<?php

namespace App\Http\Controllers\Project;

use App\Enums\HolidayRule;
use App\Enums\RecurrenceFrequency;
use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\SaveRecurringTaskRequest;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * 定期タスク（決まった日にタスクを自動で作る）の一覧と設定
 *
 * 参加者なら誰でも作って直せる（タスクと同じ扱い）。アーカイブ中のプロジェクトでは開けない。
 */
class RecurringTaskController extends Controller
{
    /** 毎朝タスクを作る時刻（routes/console.php と合わせる） */
    private const RUN_HOUR = 6;

    public function index(Project $project): View
    {
        Gate::authorize('work', $project);

        return view('recurring-tasks.index', [
            'project' => $project,
            'archiveCount' => $this->archiveCount($project),
            'recurringTasks' => $project->recurringTasks()
                ->with(['list', 'creator'])
                ->withCount('tasks')
                // 動いているものを先に、次に作る日が近い順。止めたもの・終わったものは後ろへ
                ->orderByRaw('paused_at is not null, next_run_on is null, next_run_on')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * 新しい定期タスク。?task= を付けると、そのタスクの今の中身をひな形にする
     */
    public function create(Request $request, Project $project): View
    {
        Gate::authorize('work', $project);

        $recurring = $project->recurringTasks()->make([
            'task_list_id' => $project->taskLists()->active()->orderBy('position')->value('id'),
            'label_ids' => [],
            'assignee_ids' => [],
            'follower_ids' => [],
            'checklists' => [],
            'frequency' => RecurrenceFrequency::MonthlyDay,
            'month_day' => today()->day,
            'month' => today()->month,
            'holiday_rule' => HolidayRule::Keep,
            'starts_on' => today(),
        ]);

        if ($request->filled('task')) {
            $task = $project->tasks()->findOrFail($request->integer('task'));
            $recurring->fill(RecurringTask::templateFrom($task));
        }

        return $this->form($project, $recurring);
    }

    public function store(SaveRecurringTaskRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('work', $project);

        $recurring = $project->recurringTasks()->make($request->attributesToSave());
        $recurring->created_by = $request->user()->getKey();
        $recurring->updated_by = $request->user()->getKey();
        $recurring->refreshNextRun();
        $recurring->save();

        return $this->saved($recurring, '定期タスクを作りました');
    }

    public function edit(RecurringTask $recurringTask): View
    {
        Gate::authorize('work', $recurringTask->project);

        return $this->form($recurringTask->project, $recurringTask);
    }

    public function update(SaveRecurringTaskRequest $request, RecurringTask $recurringTask): RedirectResponse
    {
        Gate::authorize('work', $recurringTask->project);

        $recurringTask->fill($request->attributesToSave());
        $recurringTask->updated_by = $request->user()->getKey();
        $recurringTask->refreshNextRun();
        $recurringTask->save();

        return $this->saved($recurringTask, '定期タスクを更新しました');
    }

    /**
     * 一時停止と再開。再開したときは、止めていた間の回を作らずに飛ばす
     */
    public function pause(Request $request, RecurringTask $recurringTask): RedirectResponse
    {
        Gate::authorize('work', $recurringTask->project);

        if ($recurringTask->isPaused()) {
            $recurringTask->paused_at = null;
            $recurringTask->skipMissed(CarbonImmutable::today());
            $recurringTask->save();

            return $this->saved($recurringTask, '再開しました');
        }

        $recurringTask->paused_at = now();
        $recurringTask->save();

        return back()->with('toast', ['message' => '一時停止しました']);
    }

    /**
     * 消しても、これまでに作ったタスクは残す
     */
    public function destroy(RecurringTask $recurringTask): RedirectResponse
    {
        $project = $recurringTask->project;

        Gate::authorize('work', $project);

        $recurringTask->delete();

        return to_route('recurring-tasks.index', $project)->with('toast', ['message' => '削除しました']);
    }

    private function form(Project $project, RecurringTask $recurring): View
    {
        return view('recurring-tasks.form', [
            'project' => $project,
            'recurring' => $recurring,
            'archiveCount' => $this->archiveCount($project),
            'lists' => $project->taskLists()->active()->orderBy('position')->get(['id', 'name', 'color']),
            'labels' => $project->labels()->orderBy('position')->get(['id', 'name', 'color']),
            'members' => $project->users()->orderBy('users.id')->get(),
        ]);
    }

    /**
     * 保存したあと。今日の分がもう作る時刻を過ぎていれば、翌朝を待たずにすぐ作る
     */
    private function saved(RecurringTask $recurring, string $message): RedirectResponse
    {
        $today = CarbonImmutable::today();
        $task = null;

        if (! $recurring->isPaused() && $recurring->next_run_on?->lessThanOrEqualTo($today) && now()->hour >= self::RUN_HOUR) {
            $task = $recurring->runDue($today);
        }

        if ($task) {
            BoardChanged::notify($recurring->project);
            $message .= "。今日の分のタスク「{$task->title}」も作りました";
        }

        return to_route('recurring-tasks.index', $recurring->project)->with('toast', ['message' => $message]);
    }

    /**
     * タブに出すアーカイブの件数（プロジェクトの画面のアーカイブタブと同じ数え方）
     */
    private function archiveCount(Project $project): int
    {
        return $project->taskLists()->archived()->count()
            + $project->tasks()->archived()->whereHas('list', fn ($query) => $query->active())->count();
    }
}

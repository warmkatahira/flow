<?php

namespace App\Http\Controllers\Task;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * タスク一覧（参加しているプロジェクト全体で、自分が担当者になっているタスク）
 *
 * ボードに出ているものだけを出す。アーカイブしたタスク・リストと、アーカイブ中のプロジェクトのものは出さない。
 */
class MyTaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $showAll = $request->query('show') === 'all';

        $query = Task::query()
            ->whereHas('assignees', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->whereNull('tasks.archived_at')
            ->whereHas('list', fn (Builder $query) => $query->active())
            ->whereHas('project', fn (Builder $query) => $query->active()
                ->whereHas('users', fn (Builder $query) => $query->whereKey($user->getKey())));

        // 完了と中止は既定では出さない。件数は切り替えのボタンに両方出す
        $openCount = (clone $query)->whereNotIn('status', self::closedStatuses())->count();
        $allCount = (clone $query)->count();

        $tasks = $query
            ->when(! $showAll, fn (Builder $query) => $query->whereNotIn('status', self::closedStatuses()))
            ->with(['project', 'list', 'labels'])
            // 終了日の近い順。終了日のないものは後ろにまとめる
            ->orderByRaw('tasks.ended_on is null')
            ->orderBy('tasks.ended_on')
            ->orderByRaw('tasks.started_on is null')
            ->orderBy('tasks.started_on')
            ->orderBy('tasks.project_id')
            ->orderBy('tasks.number')
            ->get();

        return view('tasks.index', [
            'tasks' => $tasks,
            'showAll' => $showAll,
            'openCount' => $openCount,
            'allCount' => $allCount,
        ]);
    }

    /**
     * @return list<string>
     */
    private static function closedStatuses(): array
    {
        return [TaskStatus::Done->value, TaskStatus::Canceled->value];
    }
}

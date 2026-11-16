<?php

namespace App\Models;

use App\Enums\HolidayRule;
use App\Enums\RecurrenceFrequency;
use App\Support\JapaneseCalendar;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * 定期タスク（決まった日にタスクを自動で作る）
 *
 * 作るのは毎朝 6:00 のコマンド（App\Console\Commands\CreateRecurringTasksCommand）。
 * 何回分も作り損ねていても、作るのは最新の1回だけ（同じタスクがまとめて何件もできないように）。
 *
 * 次の回は「本来の日」（土日・祝日でずらす前）で数える。ずらしたあとの日で数えると、
 * 前の平日へずらした回のあとに、本来の日がもう一度やってきて二重に作ってしまうため。
 */
#[Fillable([
    'task_list_id', 'title', 'description', 'label_ids', 'assignee_ids', 'follower_ids', 'checklists',
    'frequency', 'weekdays', 'month_day', 'month', 'nth', 'nth_weekday', 'holiday_rule',
    'due_days', 'starts_on', 'ends_on',
])]
class RecurringTask extends Model
{
    /** タイトルに書ける差し込み（一覧の案内にも使う） */
    public const PLACEHOLDERS = ['{年}', '{月}', '{日}', '{年月}', '{日付}'];

    /** 次の本来の日を探す日数の上限（毎年でも1年あれば必ず見つかる） */
    private const SEARCH_DAYS = 800;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label_ids' => 'array',
            'assignee_ids' => 'array',
            'follower_ids' => 'array',
            'checklists' => 'array',
            'weekdays' => 'array',
            'frequency' => RecurrenceFrequency::class,
            'holiday_rule' => HolidayRule::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'last_base_on' => 'immutable_date',
            'next_run_on' => 'immutable_date',
            'paused_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // tasks には外部キーを張っていないので、作ったタスクとのつながりはここで外す（タスクは残す）
        static::deleting(fn (self $recurring) => $recurring->tasks()->update(['recurring_task_id' => null]));
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'task_list_id');
    }

    /**
     * 作成者（削除済みのユーザーでも名前を出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * これまでに作ったタスク
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * 説明はタスクと同じ書式付き（RichText）
     */
    protected function description(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => RichText::sanitize($value) ?: null);
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * 終わる日を過ぎて、もう作る回がない
     */
    public function isFinished(): bool
    {
        return ! $this->isPaused() && $this->next_run_on === null;
    }

    /**
     * 作る日が来ているもの（止めていない・プロジェクトがアーカイブ中でない）
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDue(Builder $query, CarbonImmutable $today): Builder
    {
        return $query
            ->whereNull('paused_at')
            ->whereNotNull('next_run_on')
            ->whereDate('next_run_on', '<=', $today)
            ->whereHas('project', fn (Builder $query) => $query->active());
    }

    // ---------------------------------------------------------------
    // 繰り返しの日
    // ---------------------------------------------------------------

    /**
     * その日が繰り返しの「本来の日」か（土日・祝日でずらす前）
     */
    public function matches(CarbonImmutable $date): bool
    {
        // 31 日や 2/29 のように、その月にない日は月末に寄せる
        $monthDay = fn () => min((int) $this->month_day, $date->daysInMonth);

        return match ($this->frequency) {
            RecurrenceFrequency::Daily => true,
            RecurrenceFrequency::Weekdays => ! $date->isWeekend(),
            RecurrenceFrequency::Weekly => in_array($date->dayOfWeekIso, array_map('intval', $this->weekdays ?? []), true),
            RecurrenceFrequency::MonthlyDay => $date->day === $monthDay(),
            RecurrenceFrequency::MonthlyNth => $date->dayOfWeekIso === (int) $this->nth_weekday
                && ((int) $this->nth === 5 ? $date->day + 7 > $date->daysInMonth : (int) ceil($date->day / 7) === (int) $this->nth),
            RecurrenceFrequency::Yearly => $date->month === (int) $this->month && $date->day === $monthDay(),
        };
    }

    /**
     * 土日・祝日に当たったときにずらした日。作らないなら null
     */
    public function shift(CarbonImmutable $base): ?CarbonImmutable
    {
        if (JapaneseCalendar::isBusinessDay($base) || $this->holiday_rule === HolidayRule::Keep) {
            return $base;
        }

        if ($this->holiday_rule === HolidayRule::Skip || ! $this->frequency->canShift()) {
            return null;
        }

        $step = $this->holiday_rule === HolidayRule::Before ? -1 : 1;
        $date = $base;

        do {
            $date = $date->addDays($step);
        } while (! JapaneseCalendar::isBusinessDay($date));

        return $date;
    }

    /**
     * $after より後の次の回。[本来の日, 作る日] を返す。もうなければ null
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function nextOccurrence(?CarbonImmutable $after = null): ?array
    {
        $cursor = $this->searchFrom($after);

        for ($i = 0; $i < self::SEARCH_DAYS; $i++) {
            $cursor = $cursor->addDay();

            if ($this->ends_on && $cursor->greaterThan($this->ends_on)) {
                return null;
            }

            if ($this->matches($cursor) && ($run = $this->shift($cursor))) {
                return [$cursor, $run];
            }
        }

        return null;
    }

    /**
     * これから作る回を並べる（設定画面の確認用）
     *
     * @return list<CarbonImmutable>
     */
    public function upcoming(int $count): array
    {
        $dates = [];
        $after = $this->last_base_on;

        while (count($dates) < $count && ($occurrence = $this->nextOccurrence($after))) {
            [$after, $dates[]] = $occurrence;
        }

        return $dates;
    }

    /**
     * 次に作る日を計算し直す（保存はしない）
     */
    public function refreshNextRun(): void
    {
        $this->next_run_on = $this->nextOccurrence($this->last_base_on)[1] ?? null;
    }

    /**
     * 止めていた間の回は作らずに飛ばす。今日より前の回を、作ったことにして進める
     */
    public function skipMissed(CarbonImmutable $today): void
    {
        while (($occurrence = $this->nextOccurrence($this->last_base_on)) && $occurrence[1]->lessThan($today)) {
            $this->last_base_on = $occurrence[0];
        }

        $this->refreshNextRun();
    }

    /**
     * 探し始める日の前日（最後に作った回の本来の日か、作り始める日の前日の遅いほう）
     */
    private function searchFrom(?CarbonImmutable $after): CarbonImmutable
    {
        $beforeStart = $this->starts_on->subDay();

        return $after && $after->greaterThan($beforeStart) ? $after : $beforeStart;
    }

    /**
     * 一覧に出す繰り返しの説明（例: 毎月25日・土日祝は前の平日）
     */
    public function summary(): string
    {
        $weekday = fn (int $day) => ['', '月', '火', '水', '木', '金', '土', '日'][$day];
        $day = fn () => (int) $this->month_day >= 31 ? '末日' : $this->month_day.'日';

        $rule = match ($this->frequency) {
            RecurrenceFrequency::Daily => '毎日',
            RecurrenceFrequency::Weekdays => '平日（月〜金）',
            RecurrenceFrequency::Weekly => '毎週 '.collect($this->weekdays)->map(fn ($day) => (int) $day)->sort()->map($weekday)->join('・'),
            RecurrenceFrequency::MonthlyDay => '毎月'.$day(),
            RecurrenceFrequency::MonthlyNth => '毎月'.((int) $this->nth === 5 ? '最終' : '第'.$this->nth).$weekday((int) $this->nth_weekday).'曜',
            RecurrenceFrequency::Yearly => '毎年'.$this->month.'月'.$day(),
        };

        return collect([$rule, $this->holiday_rule->summary()])->filter()->join('・');
    }

    // ---------------------------------------------------------------
    // タスクを作る
    // ---------------------------------------------------------------

    /**
     * 作る日が来ていれば、最新の1回だけタスクを作って次の回へ進める
     */
    public function runDue(CarbonImmutable $today): ?Task
    {
        $latest = null;
        $after = $this->last_base_on;

        while (($occurrence = $this->nextOccurrence($after)) && $occurrence[1]->lessThanOrEqualTo($today)) {
            $latest = $occurrence;
            $after = $occurrence[0];
        }

        if (! $latest) {
            $this->refreshNextRun();
            $this->save();

            return null;
        }

        [$base, $runOn] = $latest;

        $task = DB::transaction(function () use ($base, $runOn) {
            $task = $this->createTask($runOn);

            $this->last_base_on = $base;
            $this->refreshNextRun();
            $this->save();

            return $task;
        });

        return $task;
    }

    /**
     * ひな形からタスクを1件作る。入れる先のリストがなければ先頭のリスト、リストが1つもなければ作らない
     */
    public function createTask(CarbonImmutable $runOn): ?Task
    {
        $project = Project::whereKey($this->project_id)->lockForUpdate()->firstOrFail();

        $list = $this->list && ! $this->list->isArchived() && $this->list->project_id === $project->getKey()
            ? $this->list
            : $project->taskLists()->active()->orderBy('position')->first();

        if (! $list) {
            return null;
        }

        $participants = $project->users()->pluck('users.id');
        $members = fn (?array $ids) => collect($ids)->map(fn ($id) => (int) $id)->intersect($participants)->values();

        $task = $list->tasks()->make([
            'title' => $this->titleFor($runOn),
            'description' => $this->description,
            'position' => (int) $list->tasks()->max('position') + 1,
            'started_on' => $runOn,
            'ended_on' => $this->due_days === null ? null : $runOn->addDays($this->due_days),
        ]);

        // 採番と所属はユーザー入力から入り込まないよう、fillable には載せずここで入れる
        $task->project_id = $project->getKey();
        $task->number = $project->nextTaskNumber();
        $task->recurring_task_id = $this->getKey();
        $task->created_by = $this->created_by;
        $task->updated_by = $this->created_by;
        $task->save();

        $task->labels()->sync($project->labels()->whereKey($this->label_ids ?? [])->pluck('id'));
        $task->assignees()->sync($members($this->assignee_ids));
        $task->followers()->sync($members($this->follower_ids));

        foreach (array_values($this->checklists ?? []) as $position => $checklist) {
            $copy = $task->checklists()->make(['title' => $checklist['title']]);
            $copy->position = $position;
            $copy->save();

            foreach (array_values($checklist['items'] ?? []) as $itemPosition => $item) {
                $itemCopy = $copy->items()->make(['title' => $item['title']]);
                $itemCopy->position = $itemPosition;
                $itemCopy->assignee_id = $participants->contains((int) ($item['assignee_id'] ?? 0)) ? (int) $item['assignee_id'] : null;
                $itemCopy->save();
            }
        }

        return $task;
    }

    /**
     * タイトルの差し込みを、作る日で埋める
     */
    public function titleFor(CarbonImmutable $date): string
    {
        $title = strtr($this->title, [
            '{年月}' => $date->format('Y年n月'),
            '{日付}' => $date->locale('ja')->isoFormat('M/D(ddd)'),
            '{年}' => $date->format('Y'),
            '{月}' => $date->format('n'),
            '{日}' => $date->format('j'),
        ]);

        return mb_substr($title, 0, 255);
    }

    /**
     * タスクの今の中身から、定期タスクのひな形の値を作る（タスクの詳細の「定期タスクにする」）
     *
     * @return array<string, mixed>
     */
    public static function templateFrom(Task $task): array
    {
        $task->loadMissing(['labels', 'assignees', 'followers', 'checklists.items']);

        return [
            'task_list_id' => $task->task_list_id,
            'title' => $task->title,
            'description' => $task->description,
            'label_ids' => $task->labels->modelKeys(),
            'assignee_ids' => $task->assignees->modelKeys(),
            'follower_ids' => $task->followers->modelKeys(),
            'checklists' => $task->checklists->map(fn (TaskChecklist $checklist) => [
                'title' => $checklist->title,
                'items' => $checklist->items->map(fn (TaskChecklistItem $item) => [
                    'title' => $item->title,
                    'assignee_id' => $item->assignee_id,
                ])->values()->all(),
            ])->values()->all(),
            'due_days' => $task->started_on && $task->ended_on ? (int) $task->started_on->diffInDays($task->ended_on) : null,
        ];
    }
}

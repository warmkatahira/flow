<?php

namespace Tests\Feature\Project;

use App\Enums\HolidayRule;
use App\Enums\ListColor;
use App\Enums\RecurrenceFrequency;
use App\Models\Label;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Project $project;

    private TaskList $todo;

    private TaskList $doing;

    private Label $label;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => '持ち主']);
        $this->member = User::factory()->create(['name' => '参加者']);
        $this->project = Project::factory()->ownedBy($this->owner)->create(['name' => '案件']);
        $this->project->users()->attach($this->member, ['role' => 'member']);
        $this->todo = $this->project->taskLists()->create(['name' => '開始前', 'color' => ListColor::Sky->pale(), 'position' => 0]);
        $this->doing = $this->project->taskLists()->create(['name' => '進行中', 'color' => ListColor::Emerald->pale(), 'position' => 1]);

        $this->label = $this->project->labels()->make(['name' => '経理', 'color' => ListColor::Rose->value]);
        $this->label->position = 99;
        $this->label->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recurring(array $attributes = []): RecurringTask
    {
        $recurring = $this->project->recurringTasks()->make([
            'task_list_id' => $this->doing->id,
            'title' => '月次締め（{年月}）',
            'label_ids' => [],
            'assignee_ids' => [],
            'follower_ids' => [],
            'checklists' => [],
            'frequency' => RecurrenceFrequency::MonthlyDay,
            'month_day' => 25,
            'holiday_rule' => HolidayRule::Keep,
            'starts_on' => '2026-11-01',
            ...$attributes,
        ]);
        $recurring->created_by = $this->owner->id;
        $recurring->updated_by = $this->owner->id;
        $recurring->refreshNextRun();
        $recurring->save();

        return $recurring;
    }

    /**
     * @return list<string>
     */
    private function upcoming(RecurringTask $recurring, int $count = 4): array
    {
        return collect($recurring->upcoming($count))->map(fn (CarbonImmutable $date) => $date->format('Y-m-d'))->all();
    }

    // ---------------------------------------------------------------
    // 繰り返しの日
    // ---------------------------------------------------------------

    public function test_毎月の日付は土日祝を前の平日へずらし二重には作らない(): void
    {
        // 2027/4/25 は日曜、2026/12/25 は金曜、2027/1/25 は月曜
        $recurring = $this->recurring(['holiday_rule' => HolidayRule::Before, 'starts_on' => '2027-03-01']);

        $this->assertSame(['2027-03-25', '2027-04-23', '2027-05-25', '2027-06-25'], $this->upcoming($recurring));
    }

    public function test_後の平日へずらすと祝日も飛ばす(): void
    {
        // 2026/11/22 は日曜、11/23 は勤労感謝の日（月）なので 11/24（火）
        $recurring = $this->recurring(['month_day' => 22, 'holiday_rule' => HolidayRule::After]);

        $this->assertSame('2026-11-24', $this->upcoming($recurring, 1)[0]);
    }

    public function test_月末はその月の最後の日になる(): void
    {
        $recurring = $this->recurring(['month_day' => 31, 'starts_on' => '2027-01-01']);

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'], $this->upcoming($recurring));
    }

    public function test_第n曜日と最終曜日(): void
    {
        $second = $this->recurring(['frequency' => RecurrenceFrequency::MonthlyNth, 'nth' => 2, 'nth_weekday' => 2]);
        $last = $this->recurring(['frequency' => RecurrenceFrequency::MonthlyNth, 'nth' => 5, 'nth_weekday' => 5]);

        $this->assertSame(['2026-11-10', '2026-12-08', '2027-01-12'], $this->upcoming($second, 3));
        $this->assertSame(['2026-11-27', '2026-12-25', '2027-01-29'], $this->upcoming($last, 3));
    }

    public function test_毎週と毎年(): void
    {
        $weekly = $this->recurring(['frequency' => RecurrenceFrequency::Weekly, 'weekdays' => [1, 4]]);
        $yearly = $this->recurring(['frequency' => RecurrenceFrequency::Yearly, 'month' => 4, 'month_day' => 1]);

        $this->assertSame(['2026-11-02', '2026-11-05', '2026-11-09', '2026-11-12'], $this->upcoming($weekly));
        $this->assertSame(['2027-04-01', '2028-04-03'], $this->upcoming(tap($yearly, fn ($recurring) => $recurring->holiday_rule = HolidayRule::After), 2));
    }

    public function test_平日の繰り返しで祝日を作らない設定(): void
    {
        $recurring = $this->recurring(['frequency' => RecurrenceFrequency::Weekdays, 'holiday_rule' => HolidayRule::Skip, 'starts_on' => '2026-11-20']);

        // 11/21・22 は土日、11/23 は祝日
        $this->assertSame(['2026-11-20', '2026-11-24', '2026-11-25'], $this->upcoming($recurring, 3));
    }

    public function test_終わる日を過ぎたら作らない(): void
    {
        $recurring = $this->recurring(['ends_on' => '2027-01-24']);

        $this->assertSame(['2026-11-25', '2026-12-25'], $this->upcoming($recurring));
    }

    public function test_一覧に出す説明(): void
    {
        $this->assertSame('毎月25日・土日祝は前の平日', $this->recurring(['holiday_rule' => HolidayRule::Before])->summary());
        $this->assertSame('毎週 月・木', $this->recurring(['frequency' => RecurrenceFrequency::Weekly, 'weekdays' => [4, 1]])->summary());
        $this->assertSame('毎月最終金曜', $this->recurring(['frequency' => RecurrenceFrequency::MonthlyNth, 'nth' => 5, 'nth_weekday' => 5])->summary());
        $this->assertSame('毎年2月末日', $this->recurring(['frequency' => RecurrenceFrequency::Yearly, 'month' => 2, 'month_day' => 31])->summary());
    }

    // ---------------------------------------------------------------
    // タスクを作る
    // ---------------------------------------------------------------

    public function test_作る日の朝にひな形の中身を写したタスクを作る(): void
    {
        $outsider = User::factory()->create();
        $recurring = $this->recurring([
            'description' => '<p>締めの<strong>手順</strong></p>',
            'label_ids' => [$this->label->id],
            'assignee_ids' => [$this->member->id, $outsider->id],
            'follower_ids' => [$this->owner->id],
            'due_days' => 5,
            'checklists' => [['title' => '手順', 'items' => [
                ['title' => '請求書を出す', 'assignee_id' => $this->member->id],
                ['title' => '報告する', 'assignee_id' => $outsider->id],
            ]]],
        ]);

        $this->travelTo('2026-11-25 06:00');
        $this->artisan('flow:recurring-tasks')->assertSuccessful();

        $task = Task::sole();
        $this->assertSame('月次締め（2026年11月）', $task->title);
        $this->assertSame($this->doing->id, $task->task_list_id);
        $this->assertSame($recurring->id, $task->recurring_task_id);
        $this->assertSame($this->owner->id, $task->created_by);
        $this->assertSame('2026-11-25', $task->started_on->format('Y-m-d'));
        $this->assertSame('2026-11-30', $task->ended_on->format('Y-m-d'));
        $this->assertSame('<p>締めの<strong>手順</strong></p>', $task->description);
        $this->assertSame([$this->label->id], $task->labels->modelKeys());
        // プロジェクトに参加していない人は外す
        $this->assertSame([$this->member->id], $task->assignees->modelKeys());
        $this->assertSame([$this->owner->id], $task->followers->modelKeys());

        $items = $task->checklists->sole()->items;
        $this->assertSame(['請求書を出す', '報告する'], $items->pluck('title')->all());
        $this->assertSame([$this->member->id, null], $items->pluck('assignee_id')->all());
        $this->assertFalse($items->contains('is_done', true));

        $this->assertSame('2026-12-25', $recurring->fresh()->next_run_on->format('Y-m-d'));

        // 同じ日にもう一度動かしても二重には作らない
        $this->artisan('flow:recurring-tasks')->assertSuccessful();
        $this->assertSame(1, Task::count());
    }

    public function test_作り損ねた回が溜まっていても最新の1回だけ作る(): void
    {
        $this->recurring(['frequency' => RecurrenceFrequency::Daily, 'title' => '日報 {日付}', 'starts_on' => '2026-11-01']);

        $this->travelTo('2026-11-05 06:00');
        $this->artisan('flow:recurring-tasks')->assertSuccessful();

        $this->assertSame(['日報 11/5(木)'], Task::pluck('title')->all());
    }

    public function test_入れる先のリストがなければ先頭のリストに入れる(): void
    {
        $this->recurring();
        $this->doing->archived_at = now();
        $this->doing->save();

        $this->travelTo('2026-11-25 06:00');
        $this->artisan('flow:recurring-tasks')->assertSuccessful();

        $this->assertSame($this->todo->id, Task::sole()->task_list_id);
    }

    public function test_一時停止中とアーカイブ中のプロジェクトでは作らない(): void
    {
        $paused = $this->recurring();
        $paused->paused_at = now();
        $paused->save();

        $other = Project::factory()->ownedBy($this->owner)->create(['archived_at' => now()]);
        $other->taskLists()->create(['name' => 'リスト', 'color' => ListColor::Sky->pale(), 'position' => 0]);
        $archived = $other->recurringTasks()->make([
            'title' => 'アーカイブ中', 'label_ids' => [], 'assignee_ids' => [], 'follower_ids' => [], 'checklists' => [],
            'frequency' => RecurrenceFrequency::Daily, 'holiday_rule' => HolidayRule::Keep, 'starts_on' => '2026-11-01',
        ]);
        $archived->refreshNextRun();
        $archived->save();

        $this->travelTo('2026-11-25 06:00');
        $this->artisan('flow:recurring-tasks')->assertSuccessful();

        $this->assertSame(0, Task::count());
    }

    // ---------------------------------------------------------------
    // 画面
    // ---------------------------------------------------------------

    public function test_タスクの中身をひな形にして作成画面を開ける(): void
    {
        $this->actingAs($this->owner)->post(route('tasks.store', $this->todo), ['title' => '請求書の確認']);
        $task = Task::firstWhere('title', '請求書の確認');
        $task->labels()->sync([$this->label->id]);

        $this->actingAs($this->member)
            ->get(route('recurring-tasks.create', [$this->project, 'task' => $task->id]))
            ->assertOk()
            ->assertSee('value="請求書の確認"', false)
            ->assertSee('value="'.$this->label->id.'" checked', false);
    }

    public function test_定期タスクを作ると次に作る日が決まる(): void
    {
        $this->travelTo('2026-11-16 05:00');

        $this->actingAs($this->member)->post(route('recurring-tasks.store', $this->project), [
            'title' => '週次の定例',
            'task_list_id' => $this->todo->id,
            'frequency' => 'weekly',
            'weekdays' => [1, 3],
            'holiday_rule' => 'keep',
            'starts_on' => '2026-11-16',
            'checklists' => [['title' => '準備', 'items' => [['title' => '議題を集める', 'assignee_id' => $this->owner->id]]]],
        ])->assertRedirect(route('recurring-tasks.index', $this->project));

        $recurring = RecurringTask::sole();
        $this->assertSame('2026-11-16', $recurring->next_run_on->format('Y-m-d'));
        $this->assertSame([1, 3], $recurring->weekdays);
        $this->assertNull($recurring->month_day);
        $this->assertSame($this->member->id, $recurring->created_by);
        // 朝 6:00 より前なので、今日の分はまだ作らない
        $this->assertSame(0, Task::count());
    }

    public function test_作る時刻を過ぎてから今日の分を設定したらすぐ作る(): void
    {
        $this->travelTo('2026-11-16 10:00');

        $this->actingAs($this->member)->post(route('recurring-tasks.store', $this->project), [
            'title' => '今日の点検',
            'task_list_id' => $this->todo->id,
            'frequency' => 'daily',
            'holiday_rule' => 'keep',
            'starts_on' => '2026-11-16',
        ])->assertSessionHas('toast', ['message' => '定期タスクを作りました。今日の分のタスク「今日の点検」も作りました']);

        $this->assertSame('2026-11-16', Task::sole()->started_on->format('Y-m-d'));
        $this->assertSame('2026-11-17', RecurringTask::sole()->next_run_on->format('Y-m-d'));
    }

    public function test_入力のチェック(): void
    {
        $this->travelTo('2026-11-16 05:00');

        $this->actingAs($this->member)->post(route('recurring-tasks.store', $this->project), [
            'title' => '',
            'task_list_id' => $this->todo->id,
            'frequency' => 'weekly',
            'holiday_rule' => 'keep',
            'starts_on' => '2026-11-01',
        ])->assertSessionHasErrors(['title', 'weekdays', 'starts_on']);

        $this->actingAs($this->member)->post(route('recurring-tasks.store', $this->project), [
            'title' => '日報',
            'task_list_id' => $this->todo->id,
            'frequency' => 'daily',
            'holiday_rule' => 'before',
            'starts_on' => '2026-11-16',
        ])->assertSessionHasErrors(['holiday_rule']);

        $this->assertSame(0, RecurringTask::count());
    }

    public function test_再開すると止めていた間の回は作らない(): void
    {
        $recurring = $this->recurring(['frequency' => RecurrenceFrequency::Daily, 'starts_on' => '2026-11-01']);

        $this->travelTo('2026-11-02 05:00');
        $this->actingAs($this->owner)->post(route('recurring-tasks.pause', $recurring));
        $this->assertTrue($recurring->fresh()->isPaused());

        $this->travelTo('2026-11-10 05:00');
        $this->actingAs($this->owner)->post(route('recurring-tasks.pause', $recurring));

        $recurring->refresh();
        $this->assertFalse($recurring->isPaused());
        $this->assertSame('2026-11-10', $recurring->next_run_on->format('Y-m-d'));
        $this->assertSame(0, Task::count());
    }

    public function test_削除しても作ったタスクは残る(): void
    {
        $recurring = $this->recurring();
        $this->travelTo('2026-11-25 06:00');
        $this->artisan('flow:recurring-tasks');

        $this->actingAs($this->owner)->delete(route('recurring-tasks.destroy', $recurring))->assertRedirect();

        $this->assertSame(0, RecurringTask::count());
        $this->assertNull(Task::sole()->recurring_task_id);
    }

    public function test_参加していない人は開けず操作できない(): void
    {
        $recurring = $this->recurring();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('recurring-tasks.index', $this->project))->assertNotFound();
        $this->actingAs($outsider)->get(route('recurring-tasks.edit', $recurring))->assertNotFound();
        $this->actingAs($outsider)->delete(route('recurring-tasks.destroy', $recurring))->assertNotFound();

        $this->assertSame(1, RecurringTask::count());
    }

    public function test_一覧と詳細の作成元(): void
    {
        $recurring = $this->recurring(['holiday_rule' => HolidayRule::Before]);
        $this->travelTo('2026-11-25 06:00');
        $this->artisan('flow:recurring-tasks');

        $this->actingAs($this->member)->get(route('recurring-tasks.index', $this->project))
            ->assertOk()
            ->assertSee('毎月25日・土日祝は前の平日')
            ->assertSee('2026/12/25(金)')
            ->assertSee('これまでに 1 件');

        $this->actingAs($this->member)->get(route('tasks.show', Task::sole()))
            ->assertOk()
            ->assertSee('定期タスク「')
            ->assertSee(route('recurring-tasks.edit', $recurring));
    }
}

<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private TaskList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->ownedBy($this->user)->create();
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);
    }

    private function task(string $title, ?string $start, ?string $end, TaskStatus $status = TaskStatus::Todo): Task
    {
        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => $title]);

        $task = Task::firstWhere('title', $title);
        $task->forceFill(['started_on' => $start, 'ended_on' => $end, 'status' => $status])->save();

        return $task;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function calendar(): array
    {
        return collect($this->actingAs($this->user)->getJson(route('projects.board', $this->project))->assertOk()->json('calendar'))
            ->keyBy('title')
            ->all();
    }

    public function test_期間のあるタスクだけをカレンダーに出す(): void
    {
        $task = $this->task('期間あり', '2026-11-02', '2026-11-06', TaskStatus::Done);
        $this->task('期間なし', null, null);

        $calendar = $this->calendar();

        $this->assertSame(['期間あり'], array_keys($calendar));
        $this->assertSame([
            'id' => $task->id,
            'number' => $task->number,
            'title' => '期間あり',
            'start' => '2026-11-02',
            'end' => '2026-11-06',
            'status' => 'done',
            'closed' => true,
            'due' => '2026-11-06',
            'list' => 'ToDo',
            'color' => ListColor::Sky->value,
            'assignees' => [],
            'labels' => [],
            'url' => route('tasks.show', $task),
        ], $calendar['期間あり']);
    }

    public function test_開始日だけや終了日だけのタスクはその1日に出す(): void
    {
        $this->task('開始日だけ', '2026-11-03', null);
        $this->task('終了日だけ', null, '2026-11-20');

        $calendar = $this->calendar();

        $this->assertSame(['2026-11-03', '2026-11-03', null], [$calendar['開始日だけ']['start'], $calendar['開始日だけ']['end'], $calendar['開始日だけ']['due']]);
        $this->assertSame(['2026-11-20', '2026-11-20'], [$calendar['終了日だけ']['start'], $calendar['終了日だけ']['end']]);
    }

    public function test_アーカイブしたタスクは出さない(): void
    {
        $this->task('残す', '2026-11-02', '2026-11-03');
        $this->task('消す', '2026-11-02', '2026-11-03')->forceFill(['archived_at' => now()])->save();

        $this->assertSame(['残す'], array_keys($this->calendar()));
    }

    public function test_プロジェクトの画面にカレンダーのタブがある(): void
    {
        $this->task('期間あり', '2026-11-02', '2026-11-06');

        $this->actingAs($this->user)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('カレンダー')
            ->assertSee('data-live-calendar', false)
            ->assertSee('期間あり');
    }
}

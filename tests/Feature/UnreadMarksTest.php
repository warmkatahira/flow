<?php

namespace Tests\Feature;

use App\Enums\ListColor;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 未読の通知があるタスクとプロジェクトの印（左のリスト・ボードのカード・カレンダー）
 */
class UnreadMarksTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $member;

    private Project $project;

    private TaskList $list;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => '投稿者']);
        $this->member = User::factory()->create(['name' => '参加者']);
        $this->project = Project::factory()->ownedBy($this->author)->create(['name' => '案件']);
        $this->project->users()->attach($this->member, ['role' => 'member']);
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->author)->post(route('tasks.store', $this->list), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');
    }

    private function mention(): void
    {
        $this->actingAs($this->author)->post(route('tasks.comments.store', $this->task), ['body' => '@参加者 確認して']);
    }

    public function test_通知がなければ印は出ない(): void
    {
        $this->assertSame(['tasks' => [], 'projects' => []], $this->member->unreadMarks());
    }

    public function test_メンションされたタスクとプロジェクトに印が出る(): void
    {
        $this->mention();

        $this->assertSame(['tasks' => [$this->task->id], 'projects' => [$this->project->id]], $this->member->unreadMarks());

        $this->actingAs($this->member)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread.tasks', [$this->task->id])
            ->assertJsonPath('unread.projects', [$this->project->id]);
    }

    public function test_フォローしたタスクの動きでも印が出る(): void
    {
        $this->actingAs($this->member)->post(route('tasks.follow', $this->task));
        $this->actingAs($this->author)->put(route('tasks.update', $this->task), ['title' => '新しい名前']);

        $this->assertSame([$this->task->id], $this->member->unreadMarks()['tasks']);
    }

    public function test_タスクを開くと印が消える(): void
    {
        $this->mention();

        $this->actingAs($this->member)->get(route('tasks.show', $this->task))->assertOk();

        $this->assertSame(['tasks' => [], 'projects' => []], $this->member->unreadMarks());
    }

    public function test_アーカイブしたタスクの通知には印を出さない(): void
    {
        $this->mention();
        $this->task->archive();

        $this->assertSame(['tasks' => [], 'projects' => []], $this->member->unreadMarks());
    }

    public function test_左のリストは最初から未読のプロジェクトを強調して出す(): void
    {
        $other = Project::factory()->ownedBy($this->member)->create(['name' => '別案件']);
        $this->mention();

        $html = $this->actingAs($this->member)->get(route('projects.show', $other))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('projects.show', $this->project), '/').'"[^>]*data-unread="true"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('projects.show', $other), '/').'"[^>]*data-unread="false"/',
            $html,
        );
    }

    public function test_ボードのカードとカレンダーに印の元がある(): void
    {
        $this->task->forceFill(['started_on' => '2026-11-02', 'ended_on' => '2026-11-04'])->save();

        $this->actingAs($this->member)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('$store.unread.hasTask('.$this->task->id.')', false)
            ->assertSee('$store.unread.hasTask(bar.task.id)', false)
            ->assertSee('未読あり');
    }
}

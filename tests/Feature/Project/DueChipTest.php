<?php

namespace Tests\Feature\Project;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 期限が近いタスクの印（色と文字の判定はブラウザ側。ここでは印の元を出すかどうかだけ見る）
 */
class DueChipTest extends TestCase
{
    use RefreshDatabase;

    private function taskWith(Project $project, User $user, string $title, ?string $endedOn, TaskStatus $status = TaskStatus::Todo): Task
    {
        $task = $project->taskLists()->first()->tasks()->make(['title' => $title, 'position' => 0]);
        $task->project_id = $project->id;
        $task->number = (int) $project->tasks()->max('number') + 1;
        $task->created_by = $user->id;
        $task->updated_by = $user->id;
        $task->ended_on = $endedOn;
        $task->status = $status;
        $task->save();

        return $task;
    }

    public function test_終了日のある未完了のタスクにだけ印の元を出す(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $project->createDefaultTaskLists();

        $this->taskWith($project, $user, '期限あり', '2026-11-12');
        $this->taskWith($project, $user, '期限なし', null);
        $this->taskWith($project, $user, '完了済み', '2026-11-01', TaskStatus::Done);
        $this->taskWith($project, $user, '中止', '2026-11-01', TaskStatus::Canceled);

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-due-chip'));
        $this->assertStringContainsString("dueChip('2026-11-12'", $html);
    }

    public function test_アーカイブ中のプロジェクトのボードにも出す(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $project->createDefaultTaskLists();
        $this->taskWith($project, $user, '期限あり', '2026-11-12', TaskStatus::OnHold);
        $project->archive();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('data-due-chip', false);
    }
}

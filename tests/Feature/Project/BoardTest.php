<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private function listFor(Project $project, string $name = 'ToDo', int $position = 0): TaskList
    {
        return $project->taskLists()->create([
            'name' => $name,
            'color' => ListColor::Sky->value,
            'position' => $position,
        ]);
    }

    public function test_参加者はリストを追加できる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->post(route('lists.store', $project), ['name' => 'ToDo', 'color' => ListColor::Emerald->value])
            ->assertRedirect();

        $this->assertDatabaseHas('task_lists', [
            'project_id' => $project->id,
            'name' => 'ToDo',
            'color' => ListColor::Emerald->value,
        ]);
    }

    public function test_参加していない人はリストを追加できない(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->post(route('lists.store', $project), ['name' => 'ToDo'])
            ->assertNotFound();

        $this->assertDatabaseCount('task_lists', 0);
    }

    public function test_システム管理者でも参加していなければボードを触れない(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();
        $list = $this->listFor($project);

        $systemAdmin = User::factory()->systemAdmin()->create();

        $this->actingAs($systemAdmin)->post(route('tasks.store', $list), ['title' => 'タスク'])->assertNotFound();
        $this->actingAs($systemAdmin)->post(route('lists.archive', $list))->assertNotFound();

        $this->assertFalse($list->fresh()->isArchived());
    }

    public function test_タスクの番号はプロジェクトごとの連番になる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $other = Project::factory()->ownedBy($user)->create();

        $list = $this->listFor($project);
        $otherList = $this->listFor($other);

        $this->actingAs($user)->post(route('tasks.store', $list), ['title' => '1件目']);
        $this->actingAs($user)->post(route('tasks.store', $list), ['title' => '2件目']);
        $this->actingAs($user)->post(route('tasks.store', $otherList), ['title' => '別プロジェクトの1件目']);

        $this->assertSame(1, Task::firstWhere('title', '1件目')->number);
        $this->assertSame(2, Task::firstWhere('title', '2件目')->number);
        // 別プロジェクトは番号が飛ばず 1 から始まる
        $this->assertSame(1, Task::firstWhere('title', '別プロジェクトの1件目')->number);
    }

    public function test_タスクを別のリストへ動かせる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $todo = $this->listFor($project, 'ToDo', 0);
        $doing = $this->listFor($project, '進行中', 1);

        $this->actingAs($user)->post(route('tasks.store', $todo), ['title' => '動かすタスク']);
        $task = Task::firstWhere('title', '動かすタスク');

        $this->actingAs($user)
            ->postJson(route('tasks.reorder', $project), [
                'list_id' => $doing->id,
                'ids' => [$task->id],
            ])
            ->assertOk();

        $task->refresh();
        $this->assertSame($doing->id, $task->task_list_id);
        $this->assertSame(0, $task->position);
    }

    public function test_他のプロジェクトのタスクは並べ替えで動かせない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);

        $otherProject = Project::factory()->ownedBy($user)->create();
        $otherList = $this->listFor($otherProject);
        $this->actingAs($user)->post(route('tasks.store', $otherList), ['title' => '他プロジェクトのタスク']);
        $foreign = Task::firstWhere('title', '他プロジェクトのタスク');

        $this->actingAs($user)
            ->postJson(route('tasks.reorder', $project), [
                'list_id' => $list->id,
                'ids' => [$foreign->id],
            ])
            ->assertOk();

        // 対象外なので動いていない
        $this->assertSame($otherList->id, $foreign->fresh()->task_list_id);
    }

    public function test_リストの並び順を保存できる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $first = $this->listFor($project, '1つ目', 0);
        $second = $this->listFor($project, '2つ目', 1);

        $this->actingAs($user)
            ->postJson(route('lists.reorder', $project), ['ids' => [$second->id, $first->id]])
            ->assertNoContent();

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }
}

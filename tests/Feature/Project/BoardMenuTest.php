<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private TaskList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->ownedBy($this->user)->create(['name' => '元']);
        $this->list = $this->listIn($this->project, 'ToDo', 0);
    }

    private function listIn(Project $project, string $name, int $position): TaskList
    {
        return $project->taskLists()->create(['name' => $name, 'color' => ListColor::Sky->value, 'position' => $position]);
    }

    private function taskIn(TaskList $list, string $title, int $number, int $position = 0): Task
    {
        $task = $list->tasks()->make(['title' => $title, 'position' => $position]);
        $task->project_id = $list->project_id;
        $task->number = $number;
        $task->save();

        return $task;
    }

    public function test_タスクを複製するとすぐ下に新しい番号で入る(): void
    {
        $first = $this->taskIn($this->list, '一', 1, 0);
        $second = $this->taskIn($this->list, '二', 2, 1);

        $this->actingAs($this->user)->post(route('tasks.duplicate', $first))->assertRedirect();

        $this->assertSame(
            ['一', '一 のコピー', '二'],
            $this->list->tasks()->reorder('position')->pluck('title')->all(),
        );
        $this->assertSame(3, Task::firstWhere('title', '一 のコピー')->number);
        $this->assertSame(2, $second->refresh()->position);
    }

    public function test_リストを複製するとすぐ右に中のタスクごと入る(): void
    {
        $this->taskIn($this->list, '一', 1, 0);
        $archived = $this->taskIn($this->list, 'アーカイブ済み', 2, 1);
        $archived->archive();
        $right = $this->listIn($this->project, '右', 1);

        $this->actingAs($this->user)->post(route('lists.duplicate', $this->list))->assertRedirect();

        $copy = TaskList::firstWhere('name', 'ToDo のコピー');

        $this->assertSame(1, $copy->position);
        $this->assertSame(2, $right->refresh()->position);
        $this->assertSame($this->list->color, $copy->color);
        $this->assertSame(['一'], $copy->tasks()->pluck('title')->all());
        $this->assertSame(3, $copy->tasks()->first()->number);
    }

    public function test_タスクを同じプロジェクトの別のリストへ動かしても番号は変わらない(): void
    {
        $task = $this->taskIn($this->list, '一', 7);
        $other = $this->listIn($this->project, '別', 1);
        $this->taskIn($other, '先客', 8, 3);

        $this->actingAs($this->user)->post(route('tasks.move', $task), ['list_id' => $other->id])->assertRedirect();

        $task->refresh();
        $this->assertSame($other->id, $task->task_list_id);
        $this->assertSame(7, $task->number);
        $this->assertSame(4, $task->position);
    }

    public function test_タスクを他のプロジェクトへ動かすと番号を振り直す(): void
    {
        $task = $this->taskIn($this->list, '一', 1);
        $target = Project::factory()->ownedBy($this->user)->create();
        $list = $this->listIn($target, '先', 0);
        $this->taskIn($list, '先客', 1);

        $this->actingAs($this->user)->post(route('tasks.move', $task), ['list_id' => $list->id])->assertRedirect();

        $task->refresh();
        $this->assertSame($target->id, $task->project_id);
        $this->assertSame($list->id, $task->task_list_id);
        $this->assertSame(2, $task->number);
    }

    public function test_参加していないプロジェクトへは動かせない(): void
    {
        $task = $this->taskIn($this->list, '一', 1);
        $target = Project::factory()->ownedBy(User::factory()->create())->create();
        $list = $this->listIn($target, '先', 0);

        $this->actingAs($this->user)->post(route('tasks.move', $task), ['list_id' => $list->id])->assertNotFound();
        $this->actingAs($this->user)->post(route('lists.move', $this->list), ['project_id' => $target->id])->assertNotFound();

        $this->assertSame($this->list->id, $task->refresh()->task_list_id);
        $this->assertSame($this->project->id, $this->list->refresh()->project_id);
    }

    public function test_アーカイブしたプロジェクトやリストへは動かせない(): void
    {
        $task = $this->taskIn($this->list, '一', 1);
        $archivedList = $this->listIn($this->project, '済', 1);
        $archivedList->archive();
        $target = Project::factory()->ownedBy($this->user)->create();
        $targetList = $this->listIn($target, '先', 0);
        $target->archived_at = now();
        $target->save();

        $this->actingAs($this->user)->post(route('tasks.move', $task), ['list_id' => $archivedList->id])->assertNotFound();
        $this->actingAs($this->user)->post(route('tasks.move', $task), ['list_id' => $targetList->id])->assertForbidden();
        $this->actingAs($this->user)->post(route('lists.move', $this->list), ['project_id' => $target->id])->assertForbidden();
    }

    public function test_リストを他のプロジェクトへ動かすとタスクも番号を振り直して動く(): void
    {
        $active = $this->taskIn($this->list, '一', 1);
        $archived = $this->taskIn($this->list, '済', 2);
        $archived->archive();

        $target = Project::factory()->ownedBy($this->user)->create();
        $existing = $this->listIn($target, '先', 4);
        $this->taskIn($existing, '先客', 1);

        $this->actingAs($this->user)->post(route('lists.move', $this->list), ['project_id' => $target->id])->assertRedirect();

        $this->list->refresh();
        $this->assertSame($target->id, $this->list->project_id);
        $this->assertSame(5, $this->list->position);
        $this->assertSame([$target->id, 2], [$active->refresh()->project_id, $active->number]);
        $this->assertSame([$target->id, 3], [$archived->refresh()->project_id, $archived->number]);
        $this->assertTrue($archived->isArchived());
    }

    public function test_リストを同じプロジェクトへは動かせない(): void
    {
        $this->actingAs($this->user)
            ->post(route('lists.move', $this->list), ['project_id' => $this->project->id])
            ->assertSessionHasErrors('project_id');
    }

    public function test_アーカイブ中のタスクは複製も移動もできない(): void
    {
        $task = $this->taskIn($this->list, '一', 1);
        $task->archive();

        $this->actingAs($this->user)->post(route('tasks.duplicate', $task))->assertNotFound();
        $this->actingAs($this->user)->post(route('tasks.move', $task), ['list_id' => $this->list->id])->assertNotFound();
    }

    public function test_移動先の候補は操作できるものだけ(): void
    {
        Project::factory()->ownedBy(User::factory()->create())->create(['name' => '他人の案件']);
        $archived = Project::factory()->ownedBy($this->user)->create(['name' => '終わった案件']);
        $archived->archived_at = now();
        $archived->save();
        $this->listIn($this->project, '済', 1)->archive();

        $this->actingAs($this->user)->getJson(route('projects.move-targets'))
            ->assertOk()
            ->assertExactJson([[
                'id' => $this->project->id,
                'name' => '元',
                'lists' => [['id' => $this->list->id, 'name' => 'ToDo']],
            ]]);
    }
}

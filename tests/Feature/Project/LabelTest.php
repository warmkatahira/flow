<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private TaskList $list;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->ownedBy($this->user)->create();
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');
    }

    private function label(string $name, ?Project $project = null): Label
    {
        $project ??= $this->project;
        $label = $project->labels()->make(['name' => $name, 'color' => '#ff0000']);
        $label->position = (int) $project->labels()->max('position') + 1;
        $label->save();

        return $label;
    }

    public function test_プロジェクトを作ると既定のラベルが入る(): void
    {
        $this->actingAs($this->user)->post(route('projects.store'), ['name' => '新しい案件'])->assertRedirect();

        $this->assertSame(
            ['優先度：高', '優先度：中', '優先度：低', 'メモ', '確認中', '保留中'],
            Project::firstWhere('name', '新しい案件')->labels()->pluck('name')->all(),
        );
    }

    public function test_既定のラベルはラベルがあるプロジェクトには足さない(): void
    {
        $this->label('自分のラベル');

        $this->project->createDefaultLabels();

        $this->assertSame(['自分のラベル'], $this->project->labels()->pluck('name')->all());
    }

    public function test_ラベルを追加できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.labels.store', $this->task), ['name' => '優先度:高', 'color' => '#FF0000'])
            ->assertOk()
            ->assertSee('優先度:高');

        $label = Label::firstWhere('name', '優先度:高');

        $this->assertSame($this->project->id, $label->project_id);
        $this->assertSame('#ff0000', $label->color);
    }

    public function test_同じプロジェクトに同じ名前は作れない(): void
    {
        $this->label('メモ');

        $this->actingAs($this->user)
            ->postJson(route('tasks.labels.store', $this->task), ['name' => 'メモ', 'color' => '#ff0000'])
            ->assertJsonValidationErrors('name');
    }

    public function test_別のプロジェクトなら同じ名前を作れる(): void
    {
        $this->label('メモ', Project::factory()->ownedBy($this->user)->create());

        $this->actingAs($this->user)
            ->postJson(route('tasks.labels.store', $this->task), ['name' => 'メモ', 'color' => '#ff0000'])
            ->assertOk();
    }

    public function test_ラベルをタスクに付け外しできる(): void
    {
        $first = $this->label('一');
        $second = $this->label('二');

        $this->actingAs($this->user)->put(route('tasks.labels.sync', $this->task), ['labels' => [$first->id, $second->id]])->assertOk();
        $this->assertCount(2, $this->task->labels);

        $this->actingAs($this->user)->put(route('tasks.labels.sync', $this->task), [])->assertOk();
        $this->assertCount(0, $this->task->fresh()->labels);
    }

    public function test_付けられるのは5つまで(): void
    {
        $ids = collect(range(1, 6))->map(fn (int $i) => $this->label("ラベル{$i}")->id);

        $this->actingAs($this->user)->put(route('tasks.labels.sync', $this->task), ['labels' => $ids->take(5)->all()])->assertOk();

        $this->actingAs($this->user)
            ->putJson(route('tasks.labels.sync', $this->task), ['labels' => $ids->all()])
            ->assertJsonValidationErrors('labels');

        $this->assertCount(5, $this->task->labels);
    }

    public function test_他のプロジェクトのラベルは付けられない(): void
    {
        $other = $this->label('別', Project::factory()->ownedBy($this->user)->create());

        $this->actingAs($this->user)
            ->putJson(route('tasks.labels.sync', $this->task), ['labels' => [$other->id]])
            ->assertJsonValidationErrors('labels.0');
    }

    public function test_ラベルを編集できる(): void
    {
        $label = $this->label('元');

        $this->actingAs($this->user)
            ->put(route('tasks.labels.update', [$this->task, $label]), ['name' => '元', 'color' => '#00ff00'])
            ->assertOk();

        $this->assertSame('#00ff00', $label->refresh()->color);
    }

    public function test_ラベルを消すとタスクからも外れる(): void
    {
        $label = $this->label('消す');
        $this->task->labels()->attach($label);

        $this->actingAs($this->user)->delete(route('tasks.labels.destroy', [$this->task, $label]))->assertOk();

        $this->assertModelMissing($label);
        $this->assertCount(0, $this->task->fresh()->labels);
    }

    public function test_他のプロジェクトのラベルは編集できない(): void
    {
        $other = $this->label('別', Project::factory()->ownedBy($this->user)->create());

        $this->actingAs($this->user)
            ->put(route('tasks.labels.update', [$this->task, $other]), ['name' => '変更', 'color' => '#00ff00'])
            ->assertNotFound();
        $this->actingAs($this->user)->delete(route('tasks.labels.destroy', [$this->task, $other]))->assertNotFound();

        $this->assertSame('別', $other->refresh()->name);
    }

    public function test_ラベルを並べ替えられる(): void
    {
        $first = $this->label('一');
        $second = $this->label('二');
        $other = $this->label('別', Project::factory()->ownedBy($this->user)->create());

        $this->actingAs($this->user)
            ->postJson(route('tasks.labels.reorder', $this->task), ['ids' => [$second->id, $first->id, $other->id]])
            ->assertNoContent();

        $this->assertSame(['二', '一'], $this->project->labels()->pluck('name')->all());
        $this->assertSame(1, $other->refresh()->position);
    }

    public function test_参加していない人は触れない(): void
    {
        $label = $this->label('メモ');
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('tasks.labels.store', $this->task), ['name' => '不正', 'color' => '#000000'])->assertNotFound();
        $this->actingAs($outsider)->put(route('tasks.labels.sync', $this->task), ['labels' => [$label->id]])->assertNotFound();
        $this->actingAs($outsider)->delete(route('tasks.labels.destroy', [$this->task, $label]))->assertNotFound();

        $this->assertModelExists($label);
    }

    public function test_複製するとラベルも写る(): void
    {
        $this->task->labels()->attach($this->label('メモ'));

        $this->actingAs($this->user)->post(route('tasks.duplicate', $this->task));

        $this->assertCount(1, Task::firstWhere('title', 'タスク のコピー')->labels);
    }

    public function test_他のプロジェクトへ動かすとラベルは外れる(): void
    {
        $this->task->labels()->attach($this->label('メモ'));
        $target = Project::factory()->ownedBy($this->user)->create();
        $list = $target->taskLists()->create(['name' => '先', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->user)->post(route('tasks.move', $this->task), ['list_id' => $list->id]);

        $this->assertCount(0, $this->task->fresh()->labels);
    }

    public function test_ボードの取り直しでフィルターのラベルも更新される(): void
    {
        $this->label('新しいラベル');

        $html = $this->actingAs($this->user)->getJson(route('projects.board', $this->project))->assertOk()->json('filterLabels');

        $this->assertStringContainsString('新しいラベル', $html);
    }

    public function test_カードにラベルが出る(): void
    {
        $label = $this->label('メモ');
        $this->task->labels()->attach($label);

        $this->actingAs($this->user)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('data-labels="'.$label->id.'"', false)
            ->assertSee('title="メモ"', false);
    }
}

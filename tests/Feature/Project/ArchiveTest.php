<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * プロジェクト・リスト・タスクはまずアーカイブし、完全な削除はアーカイブ済みのものだけできる
 */
class ArchiveTest extends TestCase
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

    private function taskIn(TaskList $list, User $user, string $title = 'タスク'): Task
    {
        $this->actingAs($user)->post(route('tasks.store', $list), ['title' => $title]);

        return Task::firstWhere('title', $title);
    }

    public function test_オーナーがアーカイブするとダッシュボードから消えデータは残る(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create(['name' => '終わった案件']);
        $task = $this->taskIn($this->listFor($project), $owner);

        $this->actingAs($owner)
            ->post(route('projects.archive', $project))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($project->fresh()->isArchived());
        $this->assertTrue($project->fresh()->hasParticipant($owner));
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('終わった案件');
        $this->actingAs($owner)->get(route('projects.archived'))->assertSee('終わった案件');
    }

    public function test_元に戻すとダッシュボードに戻りお気に入りも残っている(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create(['name' => '再開する案件']);
        $project->users()->updateExistingPivot($owner, ['is_favorite' => true]);
        $project->archive();

        $this->actingAs($owner)
            ->post(route('projects.unarchive', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertFalse($project->fresh()->isArchived());
        $this->assertTrue($project->fresh()->isFavoritedBy($owner));
        $this->actingAs($owner)->get(route('dashboard'))->assertSee('再開する案件');
    }

    public function test_参加者はプロジェクトをアーカイブも復元もできない(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->ownedBy(User::factory()->create())->withMembers($member)->create();

        $this->actingAs($member)->post(route('projects.archive', $project))->assertForbidden();
        $this->assertFalse($project->fresh()->isArchived());

        $project->archive();

        $this->actingAs($member)->post(route('projects.unarchive', $project))->assertForbidden();
        $this->assertTrue($project->fresh()->isArchived());
    }

    public function test_システム管理者でも参加していなければアーカイブ一覧に出ず操作もできない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $project = Project::factory()->ownedBy(User::factory()->create())->create(['name' => '秘密の案件']);

        $this->actingAs($systemAdmin)->post(route('projects.archive', $project))->assertNotFound();

        $project->archive();

        $this->actingAs($systemAdmin)->post(route('projects.unarchive', $project))->assertNotFound();
        $this->actingAs($systemAdmin)->get(route('projects.show', $project))->assertNotFound();
        $this->actingAs($systemAdmin)->get(route('projects.archived'))->assertDontSee('秘密の案件');
    }

    public function test_アーカイブ中のプロジェクトは見られるが変更できない(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create(['name' => '読み取り専用']);
        $list = $this->listFor($project);
        $task = $this->taskIn($list, $owner, '残っているタスク');
        $project->archive();

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('残っているタスク')
            ->assertDontSee('タスクを追加');

        $this->actingAs($owner)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($owner)->post(route('lists.store', $project), ['name' => '新規'])->assertForbidden();
        $this->actingAs($owner)->post(route('tasks.store', $list), ['title' => '新規'])->assertForbidden();
        $this->actingAs($owner)->put(route('tasks.update', $task), ['title' => '変更'])->assertForbidden();
        $this->actingAs($owner)->post(route('tasks.archive', $task))->assertForbidden();

        $this->assertSame('残っているタスク', $task->fresh()->title);
    }

    public function test_タスクをアーカイブするとボードから消えアーカイブタブに出る(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $task = $this->taskIn($list, $user, '片付いたタスク');

        $this->actingAs($user)->post(route('tasks.archive', $task))->assertRedirect();

        $this->assertTrue($task->fresh()->isArchived());

        $response = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();

        $this->assertCount(0, $response->viewData('project')->taskLists->first()->tasks);
        $this->assertTrue($response->viewData('archivedTasks')->contains($task));
    }

    public function test_タスクを元に戻すと元のリストの末尾に入る(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $first = $this->taskIn($list, $user, '先のタスク');
        $second = $this->taskIn($list, $user, '後のタスク');

        $first->archive();

        $this->actingAs($user)->post(route('tasks.unarchive', $first))->assertRedirect();

        $first->refresh();
        $this->assertFalse($first->isArchived());
        $this->assertSame($list->id, $first->task_list_id);
        $this->assertGreaterThan($second->fresh()->position, $first->position);
    }

    public function test_アーカイブしたタスクの番号は再利用されない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);

        $this->taskIn($list, $user, '1件目')->archive();

        $this->assertSame(2, $this->taskIn($list, $user, '2件目')->number);
    }

    public function test_アーカイブしたタスクは並べ替えで動かせない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $todo = $this->listFor($project, 'ToDo', 0);
        $doing = $this->listFor($project, '進行中', 1);
        $task = $this->taskIn($todo, $user);
        $task->archive();

        $this->actingAs($user)
            ->postJson(route('tasks.reorder', $project), ['list_id' => $doing->id, 'ids' => [$task->id]])
            ->assertOk();

        $this->assertSame($todo->id, $task->fresh()->task_list_id);
    }

    public function test_リストをアーカイブしても中のタスクは残り一緒に戻る(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $task = $this->taskIn($list, $user);

        $this->actingAs($user)->post(route('lists.archive', $list))->assertRedirect();

        $this->assertTrue($list->fresh()->isArchived());
        $this->assertFalse($task->fresh()->isArchived());

        $response = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $this->assertCount(0, $response->viewData('project')->taskLists);

        $this->actingAs($user)->post(route('lists.unarchive', $list))->assertRedirect();

        $response = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $this->assertTrue($response->viewData('project')->taskLists->first()->tasks->contains($task));
    }

    public function test_リストがアーカイブ中ならタスクの追加も単独の復元もできない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $task = $this->taskIn($list, $user);
        $task->archive();
        $list->archive();

        $this->actingAs($user)->post(route('tasks.store', $list), ['title' => '新規'])->assertNotFound();
        $this->actingAs($user)->post(route('tasks.unarchive', $task))->assertRedirect();

        $this->assertTrue($task->fresh()->isArchived());
        $this->assertDatabaseMissing('tasks', ['title' => '新規']);
    }

    public function test_オーナーはアーカイブ済みのプロジェクトを完全に削除できる(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create([
            'icon_path' => UploadedFile::fake()->image('icon.png')->store('project-icons', 'public'),
        ]);
        $task = $this->taskIn($this->listFor($project), $owner);
        $project->archive();

        $this->actingAs($owner)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.archived'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        Storage::disk('public')->assertMissing($project->icon_path);
    }

    public function test_アーカイブしていないプロジェクトは削除できない(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_参加者はアーカイブ済みのプロジェクトを削除できない(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->ownedBy(User::factory()->create())->withMembers($member)->create();
        $project->archive();

        $this->actingAs($member)->delete(route('projects.destroy', $project))->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_システム管理者でも参加していなければプロジェクトを削除できない(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();
        $project->archive();

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->delete(route('projects.destroy', $project))
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_アーカイブ済みのタスクだけ完全に削除できる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $active = $this->taskIn($list, $user, 'ボードのタスク');
        $archived = $this->taskIn($list, $user, 'アーカイブしたタスク');
        $archived->archive();

        $this->actingAs($user)->delete(route('tasks.destroy', $active))->assertNotFound();
        $this->actingAs($user)->delete(route('tasks.destroy', $archived))->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $active->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $archived->id]);
    }

    public function test_アーカイブ済みのリストだけ完全に削除でき中のタスクも消える(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $task = $this->taskIn($list, $user);

        $this->actingAs($user)->delete(route('lists.destroy', $list))->assertNotFound();
        $this->assertDatabaseHas('task_lists', ['id' => $list->id]);

        $list->archive();

        $this->actingAs($user)->delete(route('lists.destroy', $list))->assertRedirect();

        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_アーカイブ中のプロジェクトの中身は個別に削除できない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $task = $this->taskIn($this->listFor($project), $user);
        $task->archive();
        $project->archive();

        $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_タスクをまとめてアーカイブできる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $list = $this->listFor($project);
        $first = $this->taskIn($list, $user, '1件目');
        $second = $this->taskIn($list, $user, '2件目');
        $left = $this->taskIn($list, $user, '残すタスク');

        $this->actingAs($user)
            ->postJson(route('tasks.archive-many', $project), ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJson(['archived' => 2])
            ->assertSessionHas('status', '2件のタスクをアーカイブしました。');

        $this->assertTrue($first->fresh()->isArchived());
        $this->assertTrue($second->fresh()->isArchived());
        $this->assertFalse($left->fresh()->isArchived());
    }

    public function test_まとめてアーカイブに他プロジェクトのタスクを混ぜても動かない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $other = Project::factory()->ownedBy($user)->create();
        $foreign = $this->taskIn($this->listFor($other), $user);

        $this->actingAs($user)
            ->postJson(route('tasks.archive-many', $project), ['ids' => [$foreign->id]])
            ->assertJson(['archived' => 0]);

        $this->assertFalse($foreign->fresh()->isArchived());
    }

    public function test_まとめてアーカイブは参加者でなければ404でアーカイブ中は403になる(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();
        $task = $this->taskIn($this->listFor($project), $owner);

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->postJson(route('tasks.archive-many', $project), ['ids' => [$task->id]])
            ->assertNotFound();

        $project->archive();

        $this->actingAs($owner)
            ->postJson(route('tasks.archive-many', $project), ['ids' => [$task->id]])
            ->assertForbidden();

        $this->assertFalse($task->fresh()->isArchived());
    }
}

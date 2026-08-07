<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_グループを作成できる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('project-groups.store'), ['name' => '社内'])
            ->assertRedirect(route('project-groups.index'));

        $this->assertDatabaseHas('project_groups', ['name' => '社内']);
    }

    public function test_同じ名前のグループは作れない(): void
    {
        ProjectGroup::create(['name' => '社内']);

        $this->actingAs(User::factory()->create())
            ->post(route('project-groups.store'), ['name' => '社内'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ProjectGroup::count());
    }

    public function test_グループの名前を変更できる(): void
    {
        $group = ProjectGroup::create(['name' => '社内']);

        $this->actingAs(User::factory()->create())
            ->put(route('project-groups.update', $group), ['name' => '社内システム'])
            ->assertRedirect(route('project-groups.index'));

        $this->assertSame('社内システム', $group->fresh()->name);
    }

    public function test_プロジェクトはグループに属さなくてもよい(): void
    {
        $creator = User::factory()->create();

        $this->actingAs($creator)
            ->post(route('projects.store'), ['name' => 'グループなしのプロジェクト'])
            ->assertRedirect();

        $this->assertNull(Project::firstWhere('name', 'グループなしのプロジェクト')->group_id);
    }

    public function test_プロジェクトをグループに所属させられる(): void
    {
        $creator = User::factory()->create();
        $group = ProjectGroup::create(['name' => '受託']);

        $this->actingAs($creator)
            ->post(route('projects.store'), [
                'name' => '所属ありのプロジェクト',
                'group_id' => $group->id,
            ])
            ->assertRedirect();

        $this->assertSame($group->id, Project::firstWhere('name', '所属ありのプロジェクト')->group_id);
    }

    public function test_グループを削除してもプロジェクトは残りグループなしになる(): void
    {
        $owner = User::factory()->create();
        $group = ProjectGroup::create(['name' => '受託']);
        $project = Project::factory()->ownedBy($owner)->create(['group_id' => $group->id]);

        $this->actingAs($owner)
            ->delete(route('project-groups.destroy', $group))
            ->assertRedirect(route('project-groups.index'));

        $this->assertDatabaseMissing('project_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertNull($project->fresh()->group_id);
    }

    public function test_ダッシュボードにグループ名が出る(): void
    {
        $user = User::factory()->create();
        $group = ProjectGroup::create(['name' => '受託']);

        Project::factory()->ownedBy($user)->create(['name' => '所属あり', 'group_id' => $group->id]);
        Project::factory()->ownedBy($user)->create(['name' => '所属なし']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('受託')
            ->assertSee('グループなし')
            ->assertSee('所属あり')
            ->assertSee('所属なし');
    }
}

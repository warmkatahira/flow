<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * グループは個人のもの
 *
 * 作った人にしか見えず、どのプロジェクトをどのグループに入れるかも人ごとに独立している。
 */
class ProjectGroupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * user_id と position は fillable に載せていないので明示的に入れる
     */
    private function groupFor(User $user, string $name, int $position = 0): ProjectGroup
    {
        $group = new ProjectGroup(['name' => $name]);
        $group->user_id = $user->getKey();
        $group->position = $position;
        $group->save();

        return $group;
    }

    private function assignedGroupId(Project $project, User $user): ?int
    {
        return $project->users()->whereKey($user->getKey())->first()?->pivot->group_id;
    }

    public function test_グループは作った人のものになる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('project-groups.store'), ['name' => '社内'])
            ->assertRedirect(route('project-groups.index'));

        $this->assertDatabaseHas('project_groups', ['name' => '社内', 'user_id' => $user->id]);
    }

    public function test_同じ名前でも人が違えば作れる(): void
    {
        $this->groupFor(User::factory()->create(), '社内');

        $this->actingAs(User::factory()->create())
            ->post(route('project-groups.store'), ['name' => '社内'])
            ->assertRedirect(route('project-groups.index'));

        $this->assertSame(2, ProjectGroup::where('name', '社内')->count());
    }

    public function test_自分の中では同じ名前のグループは作れない(): void
    {
        $user = User::factory()->create();
        $this->groupFor($user, '社内');

        $this->actingAs($user)
            ->post(route('project-groups.store'), ['name' => '社内'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ProjectGroup::count());
    }

    public function test_グループ管理には自分のグループだけ出る(): void
    {
        $user = User::factory()->create();
        $this->groupFor($user, '自分のグループ');
        $this->groupFor(User::factory()->create(), '他人のグループ');

        $this->actingAs($user)
            ->get(route('project-groups.index'))
            ->assertOk()
            ->assertSee('自分のグループ')
            ->assertDontSee('他人のグループ');
    }

    public function test_他人のグループは編集も削除もできない(): void
    {
        $group = $this->groupFor(User::factory()->create(), '他人のグループ');
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('project-groups.edit', $group))->assertNotFound();
        $this->actingAs($stranger)->put(route('project-groups.update', $group), ['name' => '乗っ取り'])->assertNotFound();
        $this->actingAs($stranger)->delete(route('project-groups.destroy', $group))->assertNotFound();

        $this->assertSame('他人のグループ', $group->fresh()->name);
    }

    public function test_他人のグループは並べ替えの対象にならない(): void
    {
        $group = $this->groupFor(User::factory()->create(), '他人のグループ', 5);

        $this->actingAs(User::factory()->create())
            ->postJson(route('project-groups.reorder'), ['ids' => [$group->id]])
            ->assertNoContent();

        $this->assertSame(5, $group->fresh()->position);
    }

    public function test_プロジェクトは初めはグループなし(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->assertNull($this->assignedGroupId($project, $user));
    }

    public function test_プロジェクトを自分のグループに入れたり外したりできる(): void
    {
        $user = User::factory()->create();
        $group = $this->groupFor($user, '受託');
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->put(route('projects.group.update', $project), ['group_id' => $group->id])
            ->assertRedirect();

        $this->assertSame($group->id, $this->assignedGroupId($project, $user));

        $this->actingAs($user)
            ->put(route('projects.group.update', $project), ['group_id' => ''])
            ->assertRedirect();

        $this->assertNull($this->assignedGroupId($project, $user));
    }

    public function test_他人のグループには入れられない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $foreign = $this->groupFor(User::factory()->create(), '他人のグループ');

        $this->actingAs($user)
            ->put(route('projects.group.update', $project), ['group_id' => $foreign->id])
            ->assertRedirect();

        // 黙って「グループなし」に倒れる
        $this->assertNull($this->assignedGroupId($project, $user));
    }

    public function test_参加していないプロジェクトはグループに入れられない(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();
        $stranger = User::factory()->create();
        $group = $this->groupFor($stranger, '受託');

        $this->actingAs($stranger)
            ->put(route('projects.group.update', $project), ['group_id' => $group->id])
            ->assertNotFound();
    }

    public function test_割り当ては人ごとに独立している(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->withMembers($member)->create();

        $ownerGroup = $this->groupFor($owner, 'オーナーの分類');

        $this->actingAs($owner)
            ->put(route('projects.group.update', $project), ['group_id' => $ownerGroup->id])
            ->assertRedirect();

        $this->assertSame($ownerGroup->id, $this->assignedGroupId($project, $owner));
        $this->assertNull($this->assignedGroupId($project, $member));
    }

    public function test_グループを削除してもプロジェクトは残りグループなしになる(): void
    {
        $user = User::factory()->create();
        $group = $this->groupFor($user, '受託');
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)->put(route('projects.group.update', $project), ['group_id' => $group->id]);

        $this->actingAs($user)
            ->delete(route('project-groups.destroy', $group))
            ->assertRedirect(route('project-groups.index'));

        $this->assertDatabaseMissing('project_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertNull($this->assignedGroupId($project, $user));
    }

    public function test_グループを並べ替えられる(): void
    {
        $user = User::factory()->create();
        $first = $this->groupFor($user, 'あ', 0);
        $second = $this->groupFor($user, 'い', 1);

        $this->actingAs($user)
            ->postJson(route('project-groups.reorder'), ['ids' => [$second->id, $first->id]])
            ->assertNoContent();

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_ダッシュボードのグループは並び順に従う(): void
    {
        $user = User::factory()->create();

        // 名前順なら「あ」が先だが、並び順で「い」を先に持ってくる
        $a = $this->groupFor($user, 'あグループ', 1);
        $i = $this->groupFor($user, 'いグループ', 0);

        $projectA = Project::factory()->ownedBy($user)->create(['name' => 'あのプロジェクト']);
        $projectI = Project::factory()->ownedBy($user)->create(['name' => 'いのプロジェクト']);

        $this->actingAs($user)->put(route('projects.group.update', $projectA), ['group_id' => $a->id]);
        $this->actingAs($user)->put(route('projects.group.update', $projectI), ['group_id' => $i->id]);

        $sections = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('sections');

        $this->assertSame(['いグループ', 'あグループ'], $sections->keys()->all());
    }

    public function test_プロジェクトが入っていないグループもダッシュボードに出る(): void
    {
        $user = User::factory()->create();
        $this->groupFor($user, 'まだ空のグループ');
        Project::factory()->ownedBy($user)->create(['name' => 'どこにも入れていない']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('まだ空のグループ')
            ->assertSee('グループなし');
    }

    public function test_ダッシュボードにグループ名が出る(): void
    {
        $user = User::factory()->create();
        $group = $this->groupFor($user, '受託');

        $project = Project::factory()->ownedBy($user)->create(['name' => '所属あり']);
        Project::factory()->ownedBy($user)->create(['name' => '所属なし']);

        $this->actingAs($user)->put(route('projects.group.update', $project), ['group_id' => $group->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('受託')
            ->assertSee('グループなし')
            ->assertSee('所属あり')
            ->assertSee('所属なし');
    }
}

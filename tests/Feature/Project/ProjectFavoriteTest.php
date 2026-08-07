<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_お気に入りは初期状態では外れている(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->assertFalse($project->isFavoritedBy($user));
    }

    public function test_同じ操作でお気に入りを付けたり外したりできる(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->postJson(route('projects.favorite', $project))
            ->assertOk()
            ->assertJson(['is_favorite' => true]);

        $this->assertTrue($project->fresh()->isFavoritedBy($user));

        $this->actingAs($user)
            ->postJson(route('projects.favorite', $project))
            ->assertOk()
            ->assertJson(['is_favorite' => false]);

        $this->assertFalse($project->fresh()->isFavoritedBy($user));
    }

    public function test_お気に入りは人ごとに独立している(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->withMembers($member)->create();

        $this->actingAs($owner)->postJson(route('projects.favorite', $project))->assertOk();

        $this->assertTrue($project->fresh()->isFavoritedBy($owner));
        $this->assertFalse($project->fresh()->isFavoritedBy($member));
    }

    public function test_参加していないプロジェクトはお気に入りにできない(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('projects.favorite', $project))
            ->assertNotFound();
    }

    public function test_システム管理者でも参加していなければお気に入りにできない(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->postJson(route('projects.favorite', $project))
            ->assertNotFound();
    }

    public function test_参加者を更新してもお気に入りは消えない(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->postJson(route('projects.favorite', $project))->assertOk();

        // 参加者の追加は sync で行うため、pivot の is_favorite が巻き添えで消えないことを確かめる
        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'members' => [$owner->id, $member->id],
                'roles' => [
                    $owner->id => 'owner',
                    $member->id => 'member',
                ],
            ])
            ->assertRedirect();

        $this->assertTrue($project->fresh()->isFavoritedBy($owner));
    }
}

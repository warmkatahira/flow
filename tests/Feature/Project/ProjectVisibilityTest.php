<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * プロジェクトの秘匿性
 *
 * 参加していない人には存在すら知らせない。システム管理者も例外ではない。
 */
class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ダッシュボードには参加しているプロジェクトだけが出る(): void
    {
        $user = User::factory()->create();

        Project::factory()->ownedBy($user)->create(['name' => '参加しているプロジェクト']);
        Project::factory()->ownedBy(User::factory()->create())->create(['name' => '他人のプロジェクト']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('参加しているプロジェクト')
            ->assertDontSee('他人のプロジェクト');
    }

    public function test_システム管理者でも参加していないプロジェクトは一覧に出ない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();

        Project::factory()->ownedBy(User::factory()->create())->create(['name' => '営業部だけの企画']);

        $this->actingAs($systemAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('営業部だけの企画');
    }

    public function test_参加していないプロジェクトの詳細は404になる(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->get(route('projects.show', $project))
            ->assertNotFound();
    }

    public function test_システム管理者でも参加していないプロジェクトの詳細は404になる(): void
    {
        $project = Project::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->get(route('projects.show', $project))
            ->assertNotFound();
    }

    public function test_システム管理者でも参加していないプロジェクトは編集も削除もできない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $project = Project::factory()->ownedBy(User::factory()->create())->create();

        $this->actingAs($systemAdmin)->get(route('projects.edit', $project))->assertNotFound();
        $this->actingAs($systemAdmin)->delete(route('projects.destroy', $project))->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_参加者は詳細を見られるが編集はできない(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()
            ->ownedBy(User::factory()->create())
            ->withMembers($member)
            ->create();

        $this->actingAs($member)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($member)->get(route('projects.edit', $project))->assertForbidden();
    }

    public function test_オーナーは詳細も編集も開ける(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($owner)->get(route('projects.edit', $project))->assertOk();
    }
}

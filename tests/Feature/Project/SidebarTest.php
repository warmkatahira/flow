<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    private function groupFor(User $user, string $name): ProjectGroup
    {
        $group = new ProjectGroup(['name' => $name]);
        $group->user_id = $user->getKey();
        $group->position = 0;
        $group->save();

        return $group;
    }

    public function test_どの画面にも自分のプロジェクトが出る(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create(['name' => '自分の案件']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('自分の案件')
            ->assertSee(route('projects.show', $project));
    }

    public function test_参加していないプロジェクトは出ない(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        Project::factory()->ownedBy(User::factory()->create())->create(['name' => '他人の案件']);

        $this->actingAs($admin)->get(route('profile.edit'))->assertDontSee('他人の案件');
    }

    public function test_アーカイブしたプロジェクトは出ない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create(['name' => '終わった案件']);
        $project->archived_at = now();
        $project->save();

        $this->actingAs($user)->get(route('profile.edit'))->assertDontSee('終わった案件');
    }

    public function test_他の人のグループは出ない(): void
    {
        $user = User::factory()->create();
        $this->groupFor(User::factory()->create(), '他人のグループ');
        $this->groupFor($user, '自分のグループ');

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSee('自分のグループ')
            ->assertDontSee('他人のグループ');
    }

    public function test_自分のグループの中にプロジェクトが出る(): void
    {
        $user = User::factory()->create();
        $group = $this->groupFor($user, '営業');
        $grouped = Project::factory()->ownedBy($user)->create(['name' => '営業の案件']);
        Project::factory()->ownedBy($user)->create(['name' => '未所属の案件']);
        $grouped->users()->updateExistingPivot($user, ['group_id' => $group->id]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSeeInOrder(['全プロジェクト', '営業', '営業の案件', 'グループなし', '未所属の案件']);
    }

    public function test_お気に入りの初期状態を渡す(): void
    {
        $user = User::factory()->create();
        $favorite = Project::factory()->ownedBy($user)->create();
        Project::factory()->ownedBy($user)->create();
        $favorite->users()->updateExistingPivot($user, ['is_favorite' => true]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSee('$store.favorites.ids = '.Js::from([$favorite->id]), false);
    }

    public function test_アーカイブしたプロジェクトの星も消えない(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $project->users()->updateExistingPivot($user, ['is_favorite' => true]);
        $project->archived_at = now();
        $project->save();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('$store.favorites.ids = '.Js::from([$project->id]), false);
    }

    public function test_開いているプロジェクトに印が付く(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create(['name' => '開いている案件']);
        Project::factory()->ownedBy($user)->create(['name' => '別の案件']);

        $html = $this->actingAs($user)->get(route('projects.show', $project))->getContent();

        $this->assertMatchesRegularExpression('/title="開いている案件"\s+aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/title="別の案件"\s+aria-current="page"/', $html);
    }

    public function test_パスワードの変更が済むまでは出さない(): void
    {
        $user = User::factory()->create();
        Project::factory()->ownedBy($user)->create(['name' => '自分の案件']);
        $user->must_change_password = true;
        $user->save();

        $this->actingAs($user)->get(route('password.edit'))
            ->assertOk()
            ->assertDontSee('自分の案件');
    }
}

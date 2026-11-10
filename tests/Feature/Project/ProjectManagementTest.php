<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_プロジェクトを作成すると作成者がオーナーになる(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($creator)
            ->post(route('projects.store'), [
                'name' => '新しいプロジェクト',
                'members' => [$member->id],
                'roles' => [$member->id => ProjectRole::Member->value],
            ])
            ->assertRedirect();

        $project = Project::firstWhere('name', '新しいプロジェクト');

        $this->assertNotNull($project);
        $this->assertSame(ProjectRole::Owner, $project->roleOf($creator));
        $this->assertSame(ProjectRole::Member, $project->roleOf($member));
    }

    public function test_プロジェクトを作成すると最初のリストが3つできる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('projects.store'), ['name' => 'リスト付き'])
            ->assertRedirect()
            ->assertSessionHas('toast', ['message' => 'プロジェクトを作成しました']);

        $lists = Project::firstWhere('name', 'リスト付き')->taskLists;

        $this->assertSame(['開始前', '進行中', '完了'], $lists->pluck('name')->all());
        $this->assertSame(
            [ListColor::Sky->value, ListColor::Emerald->value, ListColor::Rose->value],
            $lists->pluck('color')->all(),
        );
        $this->assertSame([0, 1, 2], $lists->pluck('position')->all());
    }

    public function test_設定画面にもプロジェクトのタブがあり各タブへ戻れる(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee(route('projects.show', [$project, 'tab' => 'calendar']), false)
            ->assertSee(route('projects.show', [$project, 'tab' => 'members']), false);

        $this->actingAs($owner)
            ->get(route('projects.show', [$project, 'tab' => 'calendar']))
            ->assertOk()
            ->assertSee("tab: 'calendar'", false);
    }

    public function test_参加者に選ばれていなくても作成者は必ず参加する(): void
    {
        $creator = User::factory()->create();

        $this->actingAs($creator)
            ->post(route('projects.store'), ['name' => '自分だけ'])
            ->assertRedirect();

        $this->assertSame(ProjectRole::Owner, Project::firstWhere('name', '自分だけ')->roleOf($creator));
    }

    public function test_オーナーは参加者を追加できる(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'members' => [$owner->id, $newMember->id],
                'roles' => [
                    $owner->id => ProjectRole::Owner->value,
                    $newMember->id => ProjectRole::Member->value,
                ],
            ])
            ->assertRedirect(route('projects.edit', $project));

        $this->assertTrue($project->fresh()->hasParticipant($newMember));
    }

    public function test_参加者はプロジェクトを更新できない(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()
            ->ownedBy(User::factory()->create())
            ->withMembers($member)
            ->create(['name' => '変更前']);

        $this->actingAs($member)
            ->put(route('projects.update', $project), [
                'name' => '変更後',
                'members' => [$member->id],
                'roles' => [$member->id => ProjectRole::Owner->value],
            ])
            ->assertForbidden();

        $this->assertSame('変更前', $project->fresh()->name);
    }

    public function test_オーナーが1人もいなくなる更新は弾かれる(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'members' => [$owner->id],
                'roles' => [$owner->id => ProjectRole::Member->value],
            ])
            ->assertSessionHasErrors('members');

        $this->assertSame(ProjectRole::Owner, $project->fresh()->roleOf($owner));
    }

    public function test_アイコン画像をアップロードできる(): void
    {
        Storage::fake('public');

        $creator = User::factory()->create();

        $this->actingAs($creator)
            ->post(route('projects.store'), [
                'name' => 'アイコンつき',
                'icon' => UploadedFile::fake()->image('icon.png', 128, 128),
            ])
            ->assertRedirect();

        $project = Project::firstWhere('name', 'アイコンつき');

        $this->assertNotNull($project->icon_path);
        Storage::disk('public')->assertExists($project->icon_path);
    }

    public function test_アイコン画像を削除できる(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->create([
            'icon_path' => UploadedFile::fake()->image('icon.png')->store('project-icons', 'public'),
        ]);
        $before = $project->icon_path;

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'remove_icon' => 1,
                'members' => [$owner->id],
                'roles' => [$owner->id => ProjectRole::Owner->value],
            ])
            ->assertRedirect();

        $this->assertNull($project->fresh()->icon_path);
        Storage::disk('public')->assertMissing($before);
    }
}

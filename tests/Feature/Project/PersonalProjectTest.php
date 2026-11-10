<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * My Project（ユーザーごとに1つの本人専用のプロジェクト）
 */
class PersonalProjectTest extends TestCase
{
    use RefreshDatabase;

    private function personalOf(User $user): Project
    {
        return Project::createPersonalFor($user);
    }

    private function taskIn(Project $project, User $user, string $title = '自分の作業'): Task
    {
        $this->actingAs($user)->post(route('tasks.store', $project->taskLists()->first()), ['title' => $title]);

        return Task::firstWhere('title', $title);
    }

    public function test_ユーザーを登録すると本人がオーナーの_my_projectが作られる(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.store'), [
                'name' => '新規ユーザー',
                'email' => 'new@example.com',
                'role' => UserRole::General->value,
                'is_active' => 1,
            ]);

        $user = User::firstWhere('email', 'new@example.com');
        $project = $user->personalProject;

        $this->assertNotNull($project);
        $this->assertSame('My Project', $project->name);
        $this->assertSame([$user->id], $project->users()->pluck('users.id')->all());
        $this->assertSame(ProjectRole::Owner, $project->roleOf($user));
        $this->assertSame(3, $project->taskLists()->count());
    }

    public function test_csvで登録したユーザーにも_my_projectが作られる(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.import.store'), [
                'csv' => UploadedFile::fake()->createWithContent('users.csv', "名前,メールアドレス,権限,ステータス\n山田,yamada@example.com,一般,有効\n"),
            ]);

        $this->assertNotNull(User::firstWhere('email', 'yamada@example.com')->personalProject);
    }

    public function test_何度呼んでも_my_projectは1つだけ(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->personalOf($user)->is($this->personalOf($user)));
        $this->assertSame(1, Project::personal()->count());
    }

    public function test_my_projectはアーカイブも削除もできない(): void
    {
        $user = User::factory()->create();
        $project = $this->personalOf($user);

        $this->actingAs($user)->post(route('projects.archive', $project))->assertForbidden();
        $this->assertFalse($project->fresh()->isArchived());

        // 直接アーカイブ済みにしても消せない
        $project->archive();
        $this->actingAs($user)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_my_projectに他の人は追加できない(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->personalOf($user);

        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => '自分のメモ',
                'members' => [$user->id, $other->id],
                'roles' => [$user->id => ProjectRole::Member->value, $other->id => ProjectRole::Owner->value],
            ])
            ->assertRedirect(route('projects.edit', $project));

        // 名前は変えられるが、参加者と役割は変わらない
        $this->assertSame('自分のメモ', $project->fresh()->name);
        $this->assertSame([$user->id], $project->users()->pluck('users.id')->all());
        $this->assertSame(ProjectRole::Owner, $project->fresh()->roleOf($user));
    }

    public function test_設定画面に参加者の選択とアーカイブを出さない(): void
    {
        $user = User::factory()->create();
        $project = $this->personalOf($user);

        $this->actingAs($user)->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('他の人は参加できません')
            ->assertDontSee('name="members[]"', false)
            ->assertDontSee(route('projects.archive', $project));
    }

    public function test_ダッシュボードのいちばん上に出る(): void
    {
        $user = User::factory()->create();
        $this->personalOf($user);
        Project::factory()->ownedBy($user)->create(['name' => 'ふつうの案件']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['My Project', '自分専用', 'お気に入り', 'ふつうの案件']);
    }

    public function test_他の人の_my_projectは見られない(): void
    {
        $project = $this->personalOf(User::factory()->create());

        // システム管理者でも、ふつうの画面からは見えない（操作もできない）
        foreach ([User::factory()->create(), User::factory()->systemAdmin()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('projects.show', $project))->assertNotFound();
            $this->actingAs($viewer)->post(route('lists.store', $project), ['name' => '追加'])->assertNotFound();
        }
    }

    public function test_システム管理者は全員の_my_projectを一覧で見られる(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $owner = User::factory()->create(['name' => '山田']);
        $project = $this->personalOf($owner);
        $this->taskIn($project, $owner, '山田の作業');

        $this->actingAs($admin)->get(route('admin.my-projects.index'))
            ->assertOk()
            ->assertSee('山田')
            ->assertSee(route('admin.my-projects.show', $project));

        $this->actingAs($admin)->get(route('admin.my-projects.show', $project))
            ->assertOk()
            ->assertSee('山田の作業')
            ->assertSee('閲覧専用');

        $task = Task::firstWhere('title', '山田の作業');
        $this->actingAs($admin)->get(route('admin.my-projects.task', [$project, $task]))
            ->assertOk()
            ->assertSee('山田の作業');
    }

    public function test_システム管理のメニューはシステム管理者にだけ出る(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())->get(route('dashboard'))
            ->assertSee('システム管理')
            ->assertSee(route('admin.my-projects.index'));

        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
            ->assertDontSee('システム管理')
            ->assertDontSee(route('admin.my-projects.index'));
    }

    public function test_システム管理者以外は閲覧画面を使えない(): void
    {
        $project = $this->personalOf(User::factory()->create());

        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('admin.my-projects.index'))->assertForbidden();
            $this->actingAs($viewer)->get(route('admin.my-projects.show', $project))->assertForbidden();
        }
    }

    public function test_閲覧画面からは_my_project以外は見られない(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $other = User::factory()->create();
        $project = Project::factory()->ownedBy($other)->create();
        $project->createDefaultTaskLists();
        $personal = $this->personalOf($other);
        $task = $this->taskIn($project, $other, '別の案件の作業');

        $this->actingAs($admin)->get(route('admin.my-projects.show', $project))->assertNotFound();
        // 別のプロジェクトのタスクを My Project の下として開いても見せない
        $this->actingAs($admin)->get(route('admin.my-projects.task', [$personal, $task]))->assertNotFound();
    }

    public function test_閲覧しても何も書き込まない(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $owner = User::factory()->create();
        $project = $this->personalOf($owner);
        $task = $this->taskIn($project, $owner);
        $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'メモ']);

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (! preg_match('/^\s*select/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->actingAs($admin)->get(route('admin.my-projects.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.my-projects.show', $project))->assertOk();
        $this->actingAs($admin)->get(route('admin.my-projects.task', [$project, $task]))->assertOk();

        $this->assertSame([], $writes);
    }
}

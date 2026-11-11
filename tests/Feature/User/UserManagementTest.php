<?php

namespace Tests\Feature\User;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_システム管理者はユーザー一覧を表示できる(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();

        $this->actingAs($systemAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($systemAdmin->email);
    }

    public function test_管理者はユーザー管理にアクセスできない(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_一般はユーザー管理にアクセスできない(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_ユーザー管理のリンクはシステム管理者にだけ表示される(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())
            ->get(route('dashboard'))
            ->assertSee('ユーザー管理');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertDontSee('ユーザー管理');
    }

    public function test_ユーザー登録画面と編集画面が表示できる(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();

        $this->actingAs($systemAdmin)->get(route('users.create'))->assertOk();
        $this->actingAs($systemAdmin)->get(route('users.edit', $user))->assertOk()->assertSee($user->name);
    }

    public function test_システム管理者はユーザーを登録できる(): void
    {
        $response = $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.store'), [
                'name' => '新規ユーザー',
                'email' => 'new@example.com',
                'role' => UserRole::Admin->value,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ]);
    }

    public function test_編集ではパスワードを変更できない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();
        $before = $user->password;

        $this->actingAs($systemAdmin)
            ->put(route('users.update', $user), [
                'name' => '名前変更',
                'email' => $user->email,
                'password' => 'sent-by-hand',
                'password_confirmation' => 'sent-by-hand',
                'role' => UserRole::General->value,
                'is_active' => 1,
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('名前変更', $user->name);
        $this->assertSame($before, $user->password);
    }

    public function test_自分自身の権限は変更できない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();

        $this->actingAs($systemAdmin)
            ->put(route('users.update', $systemAdmin), [
                'name' => $systemAdmin->name,
                'email' => $systemAdmin->email,
                'role' => UserRole::General->value,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($systemAdmin->fresh()->isSystemAdmin());
    }

    public function test_ユーザーを無効にできる(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();

        $this->actingAs($systemAdmin)
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => 0,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_自分自身は無効にできない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();

        $this->actingAs($systemAdmin)
            ->put(route('users.update', $systemAdmin), [
                'name' => $systemAdmin->name,
                'email' => $systemAdmin->email,
                'role' => $systemAdmin->role->value,
                'is_active' => 0,
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($systemAdmin->fresh()->is_active);
    }

    public function test_自分自身は削除できない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();

        $this->actingAs($systemAdmin)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $systemAdmin))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $systemAdmin->id]);
    }

    public function test_他のユーザーは削除できる(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();

        $this->actingAs($systemAdmin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted($user);
    }

    public function test_削除しても参加とグループは残り元に戻せる(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();
        $project = Project::factory()->ownedBy($user)->create();
        $user->projectGroups()->create(['name' => '自分のグループ', 'position' => 1]);

        $this->actingAs($systemAdmin)->delete(route('users.destroy', $user));

        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('project_groups', ['user_id' => $user->id]);

        $this->actingAs($systemAdmin)->get(route('users.index'))->assertDontSee($user->email);
        $this->actingAs($systemAdmin)->get(route('users.deleted'))->assertSee($user->email);

        $this->actingAs($systemAdmin)
            ->post(route('users.restore', $user))
            ->assertRedirect(route('users.deleted'));

        $this->assertNotSoftDeleted($user);
        $this->assertTrue($project->fresh()->hasParticipant($user));
    }

    public function test_削除したユーザーはログインできない(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $user->delete();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_削除済みユーザーのメールアドレスでは新規登録できない(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.store'), [
                'name' => '別の人',
                'email' => $user->email,
                'role' => 'general',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_システム管理者以外は削除済みの一覧も復元も使えない(): void
    {
        $general = User::factory()->create();
        $deleted = User::factory()->create();
        $deleted->delete();

        $this->actingAs($general)->get(route('users.deleted'))->assertForbidden();
        $this->actingAs($general)->post(route('users.restore', $deleted))->assertForbidden();

        $this->assertSoftDeleted($deleted);
    }

    public function test_プロジェクトを更新しても削除済みユーザーの参加は外れない(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->ownedBy($owner)->withMembers($member)->create();
        $member->delete();

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'members' => [$owner->id],
                'roles' => [$owner->id => ProjectRole::Owner->value],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $member->id]);
    }

    public function test_ユーザー一覧にアイコンと最終ログイン日時が出る(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();

        $user = User::factory()->create();
        $user->avatar_path = 'avatars/me.png';
        $user->last_login_at = '2026-11-06 09:30:00';
        $user->save();

        $this->actingAs($systemAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($user->avatarUrl())
            ->assertSee('2026/11/06(金) 09:30')
            ->assertSee('未ログイン');
    }
}

<?php

namespace Tests\Feature\User;

use App\Enums\UserRole;
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
            ->get(route('boards.index'))
            ->assertSee('ユーザー管理');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('boards.index'))
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
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

    public function test_パスワードを空にすると変更されない(): void
    {
        $systemAdmin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();
        $before = $user->password;

        $this->actingAs($systemAdmin)
            ->put(route('users.update', $user), [
                'name' => '名前変更',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
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

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}

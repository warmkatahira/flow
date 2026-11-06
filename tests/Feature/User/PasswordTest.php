<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private function userWithIssuedPassword(): User
    {
        $user = User::factory()->create(['password' => 'issued-pass']);
        $user->must_change_password = true;
        $user->save();

        return $user;
    }

    private function issuedFrom(mixed $response): array
    {
        return $response->baseResponse->getSession()->get('issuedPasswords');
    }

    public function test_新規登録すると初期パスワードが発行され変更が必須になる(): void
    {
        $response = $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.store'), [
                'name' => '新しい人',
                'email' => 'new@example.com',
                'role' => 'general',
                'is_active' => 1,
            ])
            ->assertRedirect(route('users.index'));

        $user = User::firstWhere('email', 'new@example.com');
        [$issued] = $this->issuedFrom($response);

        $this->assertTrue($user->must_change_password);
        $this->assertSame('new@example.com', $issued['email']);
        $this->assertSame(12, strlen($issued['password']));
        $this->assertTrue(Hash::check($issued['password'], $user->password));
    }

    public function test_発行するパスワードは毎回変わる(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame($user->issuePassword(), $user->issuePassword());
    }

    public function test_発行されたパスワードは一覧に一度だけ表示される(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $response = $this->actingAs($admin)->post(route('users.password.reset', User::factory()->create()));
        [$issued] = $this->issuedFrom($response);

        $this->actingAs($admin)->get(route('users.index'))->assertSee($issued['password']);
        $this->actingAs($admin)->get(route('users.index'))->assertDontSee($issued['password']);
    }

    public function test_システム管理者はパスワードをリセットできる(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $response = $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.password.reset', $user))
            ->assertRedirect(route('users.index'));

        [$issued] = $this->issuedFrom($response);
        $user->refresh();

        $this->assertTrue($user->must_change_password);
        $this->assertFalse(Hash::check('old-password', $user->password));
        $this->assertTrue(Hash::check($issued['password'], $user->password));
    }

    public function test_自分のパスワードはリセットできない(): void
    {
        $admin = User::factory()->systemAdmin()->create(['password' => 'old-password']);

        $this->actingAs($admin)->post(route('users.password.reset', $admin))->assertRedirect();

        $admin->refresh();

        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('old-password', $admin->password));
    }

    public function test_システム管理者以外はユーザー管理を一切使えない(): void
    {
        $target = User::factory()->create(['password' => 'old-password']);
        $deleted = User::factory()->create();
        $deleted->delete();

        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user);

            $this->get(route('users.index'))->assertForbidden();
            $this->get(route('users.create'))->assertForbidden();
            $this->get(route('users.edit', $target))->assertForbidden();
            $this->get(route('users.deleted'))->assertForbidden();
            $this->get(route('users.import'))->assertForbidden();
            $this->delete(route('users.destroy', $target))->assertForbidden();
            $this->post(route('users.password.reset', $target))->assertForbidden();
        }

        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
    }

    public function test_変更が必要な間は他の画面へ行けない(): void
    {
        $user = $this->userWithIssuedPassword();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('password.edit'));
        $this->actingAs($user)->post(route('projects.store'), ['name' => 'x'])->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get(route('password.edit'))->assertOk();
    }

    public function test_変更が必要でもログアウトはできる(): void
    {
        $this->actingAs($this->userWithIssuedPassword())->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_変更すると解除されダッシュボードへ進める(): void
    {
        $user = $this->userWithIssuedPassword();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'issued-pass',
                'password' => 'my-new-password',
                'password_confirmation' => 'my-new-password',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('my-new-password', $user->password));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_現在のパスワードが違うと変更できない(): void
    {
        $user = $this->userWithIssuedPassword();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'wrong',
                'password' => 'my-new-password',
                'password_confirmation' => 'my-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_同じパスワードのままにはできない(): void
    {
        $user = $this->userWithIssuedPassword();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'issued-pass',
                'password' => 'issued-pass',
                'password_confirmation' => 'issued-pass',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_プロフィールから任意に変更できる(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->get(route('profile.edit'))->assertSee(route('password.edit'));

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'old-password',
                'password' => 'my-new-password',
                'password_confirmation' => 'my-new-password',
            ])
            ->assertRedirect(route('profile.edit'));
    }

    public function test_システム管理者は削除できない(): void
    {
        $target = User::factory()->systemAdmin()->create();

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->delete(route('users.destroy', $target))
            ->assertRedirect();

        $this->assertNotSoftDeleted($target);
    }
}

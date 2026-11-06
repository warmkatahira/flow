<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン画面が表示できる(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_本番以外ではログイン画面にテスト用アカウントが初期表示される(): void
    {
        config([
            'flow.test_account.email' => 'test@example.com',
            'flow.test_account.password' => 'test-password',
        ]);

        $this->get('/login')
            ->assertSee('test@example.com', false)
            ->assertSee('test-password', false);
    }

    public function test_未ログインならログイン画面にリダイレクトされる(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_正しいメールアドレスとパスワードでログインできる(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_無効なユーザーはログインできない(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ログイン中に無効化されるとログアウトされる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_パスワードが違うとログインできない(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_メールアドレスは必須(): void
    {
        $this->post('/login', [
            'email' => '',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ログイン失敗を繰り返すとロックされる(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
    }

    public function test_ログイン後はダッシュボードが表示できる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertSee($user->name);
    }

    public function test_ログアウトできる(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_ログインすると最終ログイン日時が記録される(): void
    {
        $this->travelTo('2026-11-06 09:30:00');

        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->assertSame('2026-11-06 09:30:00', $user->fresh()->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_ログインに失敗しても最終ログイン日時は変わらない(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $this->assertNull($user->fresh()->last_login_at);
    }
}

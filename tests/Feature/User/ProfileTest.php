<?php

namespace Tests\Feature\User;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function userWithAvatar(): User
    {
        $user = User::factory()->create();
        $user->avatar_path = UploadedFile::fake()->image('old.png')->store('avatars', 'public');
        $user->save();

        return $user;
    }

    public function test_ログインしていないとプロフィールを開けない(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_メニューからプロフィールへ飛べる(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee(route('profile.edit'));
    }

    public function test_プロフィールに自分の情報が出る(): void
    {
        $user = User::factory()->create(['name' => '山田 太郎']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('山田 太郎')
            ->assertSee($user->email);
    }

    public function test_アイコン画像を登録できる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.png', 128, 128)])
            ->assertRedirect(route('profile.edit'));

        $path = $user->fresh()->avatar_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_差し替えると古い画像は消える(): void
    {
        $user = $this->userWithAvatar();
        $before = $user->avatar_path;

        $this->actingAs($user)
            ->put(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('new.png')])
            ->assertRedirect();

        $this->assertNotSame($before, $user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($before);
    }

    public function test_アイコン画像を削除できる(): void
    {
        $user = $this->userWithAvatar();
        $before = $user->avatar_path;

        $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertRedirect();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($before);
    }

    public function test_画像でないファイルは登録できない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_2_m_bを超える画像は登録できない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('big.png')->size(2049)])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_登録したアイコンは参加者の一覧にも出る(): void
    {
        $user = $this->userWithAvatar();
        $project = Project::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertSee($user->avatarUrl());
    }

    public function test_ユーザーを削除してもアイコン画像は残る(): void
    {
        $user = $this->userWithAvatar();
        $path = $user->avatar_path;

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->delete(route('users.destroy', $user))
            ->assertRedirect();

        Storage::disk('public')->assertExists($path);
    }
}

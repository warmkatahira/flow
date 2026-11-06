<?php

namespace Tests\Feature\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "名前,メールアドレス,権限,ステータス\n";

    private function upload(string $content, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? User::factory()->systemAdmin()->create())
            ->from(route('users.import'))
            ->post(route('users.import.store'), [
                'csv' => UploadedFile::fake()->createWithContent('users.csv', $content),
            ]);
    }

    public function test_cs_vからユーザーをまとめて登録できる(): void
    {
        $this->upload(self::HEADER."山田,yamada@example.com,管理者,無効\n佐藤,sato@example.com,一般,有効\n")
            ->assertRedirect(route('users.index'));

        $yamada = User::firstWhere('email', 'yamada@example.com');

        $this->assertSame('山田', $yamada->name);
        $this->assertSame(UserRole::Admin, $yamada->role);
        $this->assertFalse($yamada->is_active);
        $this->assertTrue($yamada->must_change_password);
        $this->assertTrue(User::firstWhere('email', 'sato@example.com')->is_active);
    }

    public function test_権限とステータスは省略できる(): void
    {
        $this->upload("名前,メールアドレス\n山田,yamada@example.com\n")->assertRedirect(route('users.index'));

        $user = User::firstWhere('email', 'yamada@example.com');

        $this->assertSame(UserRole::General, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_shift_ji_sの_cs_vを読める(): void
    {
        $content = mb_convert_encoding(self::HEADER."山田,yamada@example.com,一般,有効\n", 'SJIS-win', 'UTF-8');

        $this->upload($content)->assertRedirect(route('users.index'));

        $this->assertSame('山田', User::firstWhere('email', 'yamada@example.com')->name);
    }

    public function test_bo_mつきの_cs_vを読める(): void
    {
        $this->upload("\xEF\xBB\xBF".self::HEADER."山田,yamada@example.com,一般,有効\r\n")
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'yamada@example.com']);
    }

    public function test_空行は無視する(): void
    {
        $this->upload(self::HEADER."\n山田,yamada@example.com,一般,有効\n,,,,\n")
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'yamada@example.com']);
    }

    public function test_1行でも問題があれば1件も登録しない(): void
    {
        $this->upload(self::HEADER."山田,yamada@example.com,一般,有効\n佐藤,not-an-email,社長,有効\n")
            ->assertRedirect(route('users.import'))
            ->assertSessionHas('importProblems', fn (array $problems) => array_keys($problems) === [3]
                && in_array('権限 の値が正しくありません。', $problems[3], true));

        $this->assertDatabaseMissing('users', ['email' => 'yamada@example.com']);
    }

    public function test_cs_vの中でメールアドレスが重複していると登録しない(): void
    {
        $this->upload(self::HEADER."山田,same@example.com,一般,有効\n佐藤,SAME@example.com,一般,有効\n")
            ->assertSessionHas('importProblems');

        $this->assertDatabaseMissing('users', ['name' => '山田']);
    }

    public function test_削除済みユーザーのメールアドレスも登録しない(): void
    {
        $deleted = User::factory()->create(['email' => 'gone@example.com']);
        $deleted->delete();

        $this->upload(self::HEADER."山田,gone@example.com,一般,有効\n")->assertSessionHas('importProblems');

        $this->assertDatabaseMissing('users', ['name' => '山田']);
    }

    public function test_必須の見出しがないと登録しない(): void
    {
        $this->upload("名前\n山田\n")->assertSessionHasErrors('csv');

        $this->assertDatabaseMissing('users', ['name' => '山田']);
    }

    public function test_データ行がないと登録しない(): void
    {
        $this->upload(self::HEADER)->assertSessionHasErrors('csv');
    }

    public function test_上限を超える件数は登録しない(): void
    {
        $lines = collect(range(1, 101))->map(fn (int $i) => "ユーザー{$i},user{$i}@example.com,一般,有効")->implode("\n");

        $this->upload(self::HEADER.$lines)->assertSessionHasErrors('csv');

        $this->assertDatabaseMissing('users', ['name' => 'ユーザー1']);
    }

    public function test_cs_v以外のファイルは受け付けない(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())
            ->post(route('users.import.store'), ['csv' => UploadedFile::fake()->image('users.png')])
            ->assertSessionHasErrors('csv');
    }

    public function test_システム管理者以外は使えない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('users.import'))->assertForbidden();
        $this->upload(self::HEADER."山田,yamada@example.com,一般,有効\n", $user)->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'yamada@example.com']);
    }

    public function test_雛形をダウンロードできる(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())
            ->get(route('users.import.template'))
            ->assertOk()
            ->assertDownload('users.csv');
    }

    public function test_発行したパスワードが登録後の画面へ渡される(): void
    {
        $response = $this->upload(self::HEADER."山田,yamada@example.com,一般,有効\n佐藤,sato@example.com,一般,有効\n");

        $issued = $response->baseResponse->getSession()->get('issuedPasswords');

        $this->assertCount(2, $issued);
        $this->assertTrue(Hash::check($issued[1]['password'], User::firstWhere('email', 'sato@example.com')->password));
    }

    public function test_cs_vのパスワード列は無視する(): void
    {
        $this->upload("名前,メールアドレス,パスワード\n山田,yamada@example.com,chosen-password\n");

        $this->assertFalse(Hash::check('chosen-password', User::firstWhere('email', 'yamada@example.com')->password));
    }
}

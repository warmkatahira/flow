<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * users テーブル
     */
    public function run(): void
    {
        // システム管理者（本番以外ではログイン画面の初期値にもなる）
        User::updateOrCreate(
            ['email' => config('flow.test_account.email')],
            [
                'name' => config('flow.test_account.name'),
                'password' => config('flow.test_account.password'),
                'role' => UserRole::SystemAdmin,
                'is_active' => true,
            ],
        );

        // 動作確認用のユーザーは本番では作らない
        if (app()->isProduction()) {
            return;
        }

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理者',
                'password' => 'password',
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => '一般ユーザー',
                'password' => 'password',
                'role' => UserRole::General,
                'is_active' => true,
            ],
        );
    }
}

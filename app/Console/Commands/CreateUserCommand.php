<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
                            {--name= : 名前}
                            {--email= : メールアドレス（ログインID）}
                            {--password= : パスワード}
                            {--role= : 権限（system_admin / admin / general）}';

    protected $description = 'ユーザーを作成する（コマンドからの登録用）';

    public function handle(): int
    {
        $attributes = [
            'name' => $this->option('name') ?: $this->ask('名前'),
            'email' => $this->option('email') ?: $this->ask('メールアドレス'),
            'password' => $this->option('password') ?: $this->secret('パスワード'),
            'role' => $this->option('role') ?: $this->choice('権限', array_keys(UserRole::options()), UserRole::General->value),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create($attributes);

        $this->info("ユーザーを作成しました: {$user->email}（{$user->role->label()}）");

        return self::SUCCESS;
    }
}

<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            // public ディスク上のパス。未設定なら頭文字のアバターを表示する
            $table->string('avatar_path')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // 発行したパスワードのままなら、初回ログイン時に変更させる
            $table->boolean('must_change_password')->default(false);
            // 権限（App\Enums\UserRole）
            $table->string('role')->default(UserRole::General->value);
            // 無効にするとログインできない（退職者など）
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

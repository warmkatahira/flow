<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // public ディスク上のパス。未設定なら頭文字のアバターを表示する
            $table->string('icon_path')->nullable();
            // My Project（ユーザーごとに1つ、本人専用）の持ち主。通常のプロジェクトは null
            $table->foreignId('personal_user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

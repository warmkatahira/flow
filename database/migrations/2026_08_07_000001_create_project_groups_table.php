<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プロジェクトをまとめる個人のグループ
 *
 * groups は MySQL 8 の予約語なので project_groups にしている
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_groups', function (Blueprint $table) {
            $table->id();
            // 自分のダッシュボードでの分類なので、グループは作った人のもの
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // 名前がぶつかって困るのは自分の中だけ
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_groups');
    }
};

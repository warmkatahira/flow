<?php

use App\Enums\ProjectRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プロジェクトの参加者
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 自分のダッシュボードでどのグループに入れるか（人ごと）。グループを消してもプロジェクトは残す
            $table->foreignId('group_id')->nullable()->constrained('project_groups')->nullOnDelete();
            $table->string('role')->default(ProjectRole::Member->value);
            // お気に入り。参加していないプロジェクトはそもそも見えないので、参加者の行に持たせる
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * グループを個人のものにする
     *
     * それまでは全員共通で、所属も projects.group_id にプロジェクト単位で持っていた。
     * 「自分のダッシュボードでの分類」なので、グループは作った人のもの、
     * 所属は project_user（人とプロジェクトの組）に持たせる。
     */
    public function up(): void
    {
        // プロジェクト共通の所属をやめる
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });

        // 既存のグループは全員共通で作られたものなので引き継がない
        DB::table('project_groups')->delete();

        Schema::table('project_groups', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['name']);
            // 名前がぶつかって困るのは自分の中だけ
            $table->unique(['user_id', 'name']);
        });

        // 所属は人ごとに持つ
        Schema::table('project_user', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('user_id')
                ->constrained('project_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });

        DB::table('project_groups')->delete();

        Schema::table('project_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropUnique(['user_id', 'name']);
            $table->unique('name');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('name')
                ->constrained('project_groups')
                ->nullOnDelete();
        });
    }
};

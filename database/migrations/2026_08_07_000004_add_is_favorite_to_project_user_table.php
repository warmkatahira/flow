<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * お気に入りは「誰が」「どのプロジェクトを」なので、参加者の中間テーブルに持たせる。
     * 参加していないプロジェクトはそもそも見えないため、これで足りる。
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }
};

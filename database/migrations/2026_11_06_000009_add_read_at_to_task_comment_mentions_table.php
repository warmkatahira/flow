<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * メンションを通知として扱うので、読んだかどうかを持たせる
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comment_mentions', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_comment_mentions', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};

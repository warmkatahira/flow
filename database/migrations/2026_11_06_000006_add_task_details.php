<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * タスクの詳細画面で扱う情報（ステータス・期間・作成者・担当者・チェックリスト・コメント）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->default('todo')->after('description');
            $table->date('started_on')->nullable()->after('status');
            $table->date('ended_on')->nullable()->after('started_on');
            // 今までのタスクは作成者が分からないので null を許す
            $table->foreignId('created_by')->nullable()->after('ended_on')->constrained('users')->nullOnDelete();
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_user');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['status', 'started_on', 'ended_on']);
        });
    }
};

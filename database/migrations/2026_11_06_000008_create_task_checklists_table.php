<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * タスクの中に名前付きのチェックリストを複数持てるようにする
 *
 * タスク > チェックリスト > 項目 の3段にする。今ある項目は「チェックリスト」という名前の1枠へ入れる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->foreignId('task_checklist_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('task_checklist_items')->distinct()->pluck('task_id') as $taskId) {
            $checklistId = DB::table('task_checklists')->insertGetId([
                'task_id' => $taskId,
                'title' => 'チェックリスト',
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('task_checklist_items')->where('task_id', $taskId)->update(['task_checklist_id' => $checklistId]);
        }

        Schema::table('task_checklist_items', function (Blueprint $table) {
            // 外部キーが索引を使っているので、先に外す
            $table->dropForeign(['task_id']);
            $table->dropIndex(['task_id', 'position']);
            $table->dropColumn('task_id');
            $table->foreignId('task_checklist_id')->nullable(false)->change();
            $table->index(['task_checklist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('task_checklists')->get() as $checklist) {
            DB::table('task_checklist_items')->where('task_checklist_id', $checklist->id)->update(['task_id' => $checklist->task_id]);
        }

        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->dropForeign(['task_checklist_id']);
            $table->dropIndex(['task_checklist_id', 'position']);
            $table->dropColumn('task_checklist_id');
            $table->foreignId('task_id')->nullable(false)->change();
            $table->index(['task_id', 'position']);
        });

        Schema::dropIfExists('task_checklists');
    }
};

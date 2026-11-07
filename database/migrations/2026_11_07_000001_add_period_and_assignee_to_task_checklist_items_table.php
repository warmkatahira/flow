<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * チェックリストの項目に期間と担当者（1人）を持たせる。どちらも任意
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->date('started_on')->nullable()->after('is_done');
            $table->date('ended_on')->nullable()->after('started_on');
            $table->foreignId('assignee_id')->nullable()->after('ended_on')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropColumn(['started_on', 'ended_on']);
        });
    }
};

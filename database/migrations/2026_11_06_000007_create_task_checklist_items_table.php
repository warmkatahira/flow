<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * チェックリストの項目
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['task_checklist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');
    }
};

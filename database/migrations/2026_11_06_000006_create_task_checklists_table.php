<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * タスクの中のチェックリスト（枠）
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
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklists');
    }
};

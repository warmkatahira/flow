<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * タスクに付けたラベル
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['label_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_task');
    }
};

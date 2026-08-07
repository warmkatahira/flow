<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // リストをまたいで動かすので、プロジェクトも直接持たせておく
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_list_id')->constrained()->cascadeOnDelete();
            // プロジェクトごとの連番（#74 のように表示する）
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->index(['task_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

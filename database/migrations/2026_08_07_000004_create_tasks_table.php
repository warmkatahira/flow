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
            // 書式付き（App\Support\RichText の HTML）。タグの分だけ長くなるので mediumText
            $table->mediumText('description')->nullable();
            // App\Enums\TaskStatus
            $table->string('status')->default('todo');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // 最後に中身を変えた人。並べ替えとコメントでは変えない（updated_at も同じ）
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->timestamp('archived_at')->nullable()->index();

            $table->unique(['project_id', 'number']);
            $table->index(['task_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

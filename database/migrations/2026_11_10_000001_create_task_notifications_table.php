<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * フォローしたタスクの動きの通知（受け取る人ごとに1行）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_notifications', function (Blueprint $table) {
            $table->id();
            // 受け取る人
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // 動かした人
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            // App\Enums\TaskActivity
            $table->string('type', 20);
            $table->string('detail')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_notifications');
    }
};

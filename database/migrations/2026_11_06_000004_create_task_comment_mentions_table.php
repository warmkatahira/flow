<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コメントでメンションされた人
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // メンションは通知として扱うので、読んだかどうかを持たせる
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['task_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comment_mentions');
    }
};

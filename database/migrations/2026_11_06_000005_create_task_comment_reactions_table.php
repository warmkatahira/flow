<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コメントへのリアクション（1コメントに1人1つ）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // utf8mb4_unicode_ci だと絵文字どうしが同じ文字として比べられる
            $table->string('emoji', 16)->collation('utf8mb4_bin');
            $table->timestamps();

            $table->unique(['task_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comment_reactions');
    }
};

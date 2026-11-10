<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コメントの添付ファイル（ファイルの中身は App\Models\TaskCommentAttachment::DISK に置く）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_comment_id')->constrained()->cascadeOnDelete();
            // アップロードしたときのファイル名（ダウンロードするときの名前）
            $table->string('name');
            // ディスク上のパス
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comment_attachments');
    }
};

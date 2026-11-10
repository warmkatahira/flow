<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * コメントの添付ファイル
 *
 * 参加していない人に見せないよう公開ディスクには置かず、TaskCommentAttachmentController を通して渡す。
 * 値はどれもサーバーが決めるので、fillable には何も載せない。
 */
class TaskCommentAttachment extends Model
{
    public const DISK = 'local';

    /** 1つのコメントに付けられる数 */
    public const MAX_FILES = 5;

    /** 1ファイルの上限（KB） */
    public const MAX_KILOBYTES = 10240;

    /**
     * 画面の中にそのまま出す画像。SVG はスクリプトを含められるので、ここに入れずダウンロードさせる
     */
    private const INLINE_IMAGES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * @return BelongsTo<TaskComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    /**
     * タスクごとのフォルダ（タスクを消すときにまとめて消す）
     */
    public static function directoryFor(int $taskId): string
    {
        return "comment-attachments/{$taskId}";
    }

    /**
     * ファイルを保存して、コメントに付ける
     */
    public static function storeFor(TaskComment $comment, UploadedFile $file): self
    {
        $attachment = new self;
        $attachment->task_comment_id = $comment->getKey();
        $attachment->name = mb_strimwidth($file->getClientOriginalName(), 0, 255);
        $attachment->path = $file->store(self::directoryFor($comment->task_id), self::DISK);
        $attachment->mime_type = $file->getMimeType() ?? 'application/octet-stream';
        $attachment->size = $file->getSize();
        $attachment->save();

        return $attachment;
    }

    public function isInlineImage(): bool
    {
        return in_array($this->mime_type, self::INLINE_IMAGES, true);
    }

    /**
     * PDF は画面の中でプレビューする（ブラウザの PDF ビューアはスクリプトを動かさない）
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * ブラウザで開いて見せられるか（それ以外は必ずダウンロードさせる）
     */
    public function isPreviewable(): bool
    {
        return $this->isInlineImage() || $this->isPdf();
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, maxPrecision: 1);
    }

    public function deleteFile(): void
    {
        Storage::disk(self::DISK)->delete($this->path);
    }
}

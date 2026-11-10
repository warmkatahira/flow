<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\TaskCommentAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * コメントの添付ファイルを渡す（参加者だけ）
 */
class TaskCommentAttachmentController extends Controller
{
    public function show(TaskCommentAttachment $attachment): StreamedResponse
    {
        $task = $attachment->comment->task;

        Gate::authorize('view', $task->project);

        $disk = Storage::disk(TaskCommentAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        // 画像は画面の中に出す。それ以外は、ブラウザに開かせず必ずダウンロードさせる
        $disposition = $attachment->isInlineImage() ? 'inline' : 'attachment';

        return $disk->response($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->isInlineImage() ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $disposition);
    }
}

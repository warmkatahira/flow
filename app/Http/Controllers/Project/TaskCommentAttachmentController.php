<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\TaskCommentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * コメントの添付ファイルを渡す（参加者だけ）
 */
class TaskCommentAttachmentController extends Controller
{
    /**
     * 画像と PDF はブラウザで開く（?download=1 ならダウンロード）。それ以外は必ずダウンロードさせる
     */
    public function show(Request $request, TaskCommentAttachment $attachment): StreamedResponse
    {
        $task = $attachment->comment->task;

        Gate::authorize('view', $task->project);

        $disk = Storage::disk(TaskCommentAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        $inline = $attachment->isPreviewable() && ! $request->boolean('download');

        return $disk->response($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->isPreviewable() ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }
}

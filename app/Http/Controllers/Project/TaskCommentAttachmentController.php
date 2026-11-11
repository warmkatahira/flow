<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\TaskCommentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        return $attachment->toDownloadResponse($request->boolean('download'));
    }
}

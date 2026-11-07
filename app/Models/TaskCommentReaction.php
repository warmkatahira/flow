<?php

namespace App\Models;

use App\Enums\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCommentReaction extends Model
{
    protected function casts(): array
    {
        return [
            'emoji' => Reaction::class,
        ];
    }

    /**
     * 付けた人（削除済みのユーザーも名前を出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

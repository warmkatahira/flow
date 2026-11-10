<?php

namespace App\Models;

use App\Enums\TaskActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * フォローしたタスクの動きの通知（受け取る人ごとに1行）
 *
 * 値はどれもサーバーが決めるので、fillable には何も載せない。
 */
class TaskNotification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskActivity::class,
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * 動かした人（削除済みのユーザーでも名前を出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function message(): string
    {
        return $this->type->message($this->detail);
    }
}

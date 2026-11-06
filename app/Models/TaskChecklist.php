<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * タスクの中のチェックリスト（1つの枠）
 */
#[Fillable(['title'])]
class TaskChecklist extends Model
{
    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    /**
     * 完了した割合（%）。読み込み済みの items から計算する
     */
    public function progress(): int
    {
        return $this->items->isEmpty()
            ? 0
            : (int) round($this->items->where('is_done', true)->count() / $this->items->count() * 100);
    }
}

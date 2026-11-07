<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['title', 'is_done', 'started_on', 'ended_on'])]
class TaskChecklistItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TaskChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'task_checklist_id');
    }

    /**
     * 担当者（プロジェクトの参加者から1人。削除済みのユーザーも名前は出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    /**
     * 項目の横に出す短い期間。今年なら年を省く（例: 11/7(土) 〜 11/9(月)）
     */
    public function periodLabel(): ?string
    {
        if (! $this->started_on && ! $this->ended_on) {
            return null;
        }

        $format = fn (?Carbon $date) => $date?->locale('ja')->isoFormat($date->isCurrentYear() ? 'M/D(ddd)' : 'YYYY/M/D(ddd)');

        return trim($format($this->started_on).' 〜 '.$format($this->ended_on));
    }
}

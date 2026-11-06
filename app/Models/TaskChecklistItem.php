<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'is_done'])]
class TaskChecklistItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TaskChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'task_checklist_id');
    }
}

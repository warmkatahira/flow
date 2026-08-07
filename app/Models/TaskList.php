<?php

namespace App\Models;

use App\Enums\ListColor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * カンバンのリスト（列）
 */
#[Fillable(['name', 'color', 'position'])]
class TaskList extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => ListColor::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }
}

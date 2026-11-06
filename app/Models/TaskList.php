<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * カンバンのリスト（列）
 */
#[Fillable(['name', 'color', 'position'])]
class TaskList extends Model
{
    use Archivable;

    /**
     * カラーコード（#rrggbb）。比べやすいよう小文字に揃える
     *
     * @return Attribute<string, string>
     */
    protected function color(): Attribute
    {
        return Attribute::set(fn (string $value) => strtolower($value));
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

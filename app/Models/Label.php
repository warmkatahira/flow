<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * タスクに付けるラベル（種類はプロジェクトごと）
 */
#[Fillable(['name', 'color'])]
class Label extends Model
{
    /** 1つのタスクに付けられる数 */
    public const MAX_PER_TASK = 5;

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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * プロジェクトをまとめるグループ
 *
 * 持っているのは名前と並び順だけ。プロジェクト側の所属は任意で、
 * グループを削除してもプロジェクトは「グループなし」として残る。
 */
#[Fillable(['name'])]
class ProjectGroup extends Model
{
    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'group_id');
    }

    /**
     * 並び順（同じなら名前順）
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }
}

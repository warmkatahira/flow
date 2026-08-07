<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * プロジェクトをまとめるグループ
 *
 * 持っているのは名前だけ。プロジェクト側の所属は任意で、
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
}

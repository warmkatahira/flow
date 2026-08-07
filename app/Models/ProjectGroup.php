<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * プロジェクトをまとめるグループ
 *
 * グループは作った人のもので、他の人からは見えない。
 * 「どのプロジェクトをどのグループに入れるか」も人ごとなので、
 * 所属は project_user.group_id が持つ。
 */
#[Fillable(['name'])]
class ProjectGroup extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

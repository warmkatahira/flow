<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'group_id', 'icon_path'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * 所属グループ（任意。未所属なら null）
     *
     * @return BelongsTo<ProjectGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProjectGroup::class, 'group_id');
    }

    /**
     * 参加者
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'is_favorite')
            ->withTimestamps();
    }

    /**
     * 自分が参加しているプロジェクトだけに絞る
     *
     * プロジェクトは秘匿性を優先しているため、システム管理者であっても
     * 参加していないプロジェクトは一覧にも詳細にも出さない。
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('users', fn (Builder $q) => $q->whereKey($user->getKey()));
    }

    /**
     * 参加しているか
     */
    public function hasParticipant(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    /**
     * オーナーか
     */
    public function isOwner(User $user): bool
    {
        return $this->users()
            ->wherePivot('role', ProjectRole::Owner->value)
            ->whereKey($user->getKey())
            ->exists();
    }

    /**
     * そのユーザーのプロジェクト内での役割（参加していなければ null）
     *
     * users を eager load 済みならクエリを投げずに済ませる
     */
    public function roleOf(User $user): ?ProjectRole
    {
        $participant = $this->relationLoaded('users')
            ? $this->users->firstWhere('id', $user->getKey())
            : $this->users()->whereKey($user->getKey())->first();

        return $participant ? ProjectRole::tryFrom($participant->pivot->role) : null;
    }

    /**
     * そのユーザーがお気に入りにしているか
     */
    public function isFavoritedBy(User $user): bool
    {
        $participant = $this->relationLoaded('users')
            ? $this->users->firstWhere('id', $user->getKey())
            : $this->users()->whereKey($user->getKey())->first();

        return (bool) $participant?->pivot->is_favorite;
    }

    /**
     * アイコン画像の URL（未設定なら null）
     */
    public function iconUrl(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }

    /**
     * アイコン画像がないときに出す頭文字
     */
    public function initial(): string
    {
        return mb_substr(trim($this->name), 0, 1) ?: '?';
    }

    /**
     * 頭文字アバターの配色。名前から決めるので同じプロジェクトは常に同じ色になる
     */
    public function initialColorClass(): string
    {
        $palette = [
            'bg-sky-500',
            'bg-cyan-500',
            'bg-teal-500',
            'bg-indigo-500',
            'bg-violet-500',
            'bg-rose-500',
            'bg-amber-500',
            'bg-emerald-500',
        ];

        return $palette[crc32($this->name) % count($palette)];
    }
}

<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'icon_path'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * カンバンのリスト（列）。1プロジェクト＝1ボードなので直下に持つ
     *
     * @return HasMany<TaskList, $this>
     */
    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * 次のタスク番号（プロジェクトごとの連番）
     *
     * 同時に作られても重複しないよう、呼ぶ側でトランザクション＋行ロックをかける。
     * 万一すり抜けても tasks の (project_id, number) のユニーク制約で弾かれる。
     */
    public function nextTaskNumber(): int
    {
        return (int) $this->tasks()->max('number') + 1;
    }

    /**
     * 参加者
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'is_favorite', 'group_id')
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

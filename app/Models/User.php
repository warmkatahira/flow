<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * 参加しているプロジェクト
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role', 'is_favorite', 'group_id')
            ->withTimestamps();
    }

    /**
     * 自分で作ったプロジェクトのグループ（他の人からは見えない）
     *
     * @return HasMany<ProjectGroup, $this>
     */
    public function projectGroups(): HasMany
    {
        return $this->hasMany(ProjectGroup::class);
    }

    /**
     * パスワードを発行して保存し、平文を返す（本人の初回ログイン時に変更が必須になる）
     *
     * 読み間違えやすい文字（0 O 1 l I）は使わない。
     */
    public function issuePassword(): string
    {
        $characters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $password = implode('', array_map(
            fn () => $characters[random_int(0, strlen($characters) - 1)],
            range(1, 12),
        ));

        $this->password = $password;
        $this->must_change_password = true;
        $this->save();

        return $password;
    }

    /**
     * アイコン画像の URL（未設定なら null）
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /**
     * システム管理者か
     */
    public function isSystemAdmin(): bool
    {
        return $this->role === UserRole::SystemAdmin;
    }

    /**
     * 管理者か
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * 一般か
     */
    public function isGeneral(): bool
    {
        return $this->role === UserRole::General;
    }
}

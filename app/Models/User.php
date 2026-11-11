<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
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
     * 自分専用の My Project（ユーザーを作ると一緒に作られる）
     *
     * @return HasOne<Project, $this>
     */
    public function personalProject(): HasOne
    {
        return $this->hasOne(Project::class, 'personal_user_id');
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
     * 自分へのメンション（通知）
     *
     * 自分で自分をメンションしたものと、今は見られないタスクのものは出さない。
     *
     * @return BelongsToMany<TaskComment, $this>
     */
    public function mentionedComments(): BelongsToMany
    {
        return $this->belongsToMany(TaskComment::class, 'task_comment_mentions')
            ->withPivot('read_at')
            ->withTimestamps()
            ->whereColumn('task_comments.user_id', '!=', 'task_comment_mentions.user_id')
            ->whereHas('task', fn ($tasks) => $tasks->active()
                ->whereHas('list', fn ($lists) => $lists->active())
                ->whereHas('project', fn ($projects) => $projects->active()->visibleTo($this)));
    }

    public function unreadMentionCount(): int
    {
        return $this->mentionedComments()->wherePivotNull('read_at')->count();
    }

    /**
     * フォローしているタスク
     *
     * @return BelongsToMany<Task, $this>
     */
    public function followedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_follows')->withTimestamps();
    }

    /**
     * フォローしたタスクの動きの通知
     *
     * アーカイブされたことも知らせるので、タスクとリストがアーカイブ中でも出す。
     * プロジェクトがアーカイブ中か、参加していないものは出さない。
     *
     * @return HasMany<TaskNotification, $this>
     */
    public function taskNotifications(): HasMany
    {
        return $this->hasMany(TaskNotification::class)
            ->whereHas('task.project', fn ($projects) => $projects->active()->visibleTo($this));
    }

    /**
     * ベルに出す未読の件数（メンションとフォローの通知の合計）
     */
    public function unreadNotificationCount(): int
    {
        return $this->unreadMentionCount() + $this->taskNotifications()->whereNull('read_at')->count();
    }

    /**
     * 未読の通知があるタスクとそのプロジェクト（左のリスト・ボードのカード・カレンダーの印に使う）
     *
     * 印を押して開けるものだけにするため、ボードに出ているタスク（アーカイブ中でない）に限る。
     * アーカイブされたタスクの通知はベルの一覧から開く。
     *
     * @return array{tasks: list<int>, projects: list<int>}
     */
    public function unreadMarks(): array
    {
        $mentioned = $this->mentionedComments()->wherePivotNull('read_at')->pluck('task_comments.task_id');
        $followed = $this->taskNotifications()->whereNull('read_at')->pluck('task_id');

        $tasks = Task::query()
            ->whereKey($mentioned->merge($followed)->unique()->values())
            ->active()
            ->whereHas('list', fn ($lists) => $lists->active())
            ->pluck('project_id', 'id');

        return [
            'tasks' => $tasks->keys()->sort()->values()->all(),
            'projects' => $tasks->values()->unique()->sort()->values()->all(),
        ];
    }

    /**
     * メンションとフォローの通知を既読にする（タスクを渡すとそのタスクの分だけ）
     */
    public function markNotificationsRead(?Task $task = null): void
    {
        $this->markMentionsRead($task);

        $this->hasMany(TaskNotification::class)
            ->whereNull('read_at')
            ->when($task, fn ($query) => $query->where('task_id', $task->getKey()))
            ->update(['read_at' => now()]);
    }

    /**
     * 自分へのメンションを既読にする（タスクを渡すとそのタスクの分だけ）
     */
    public function markMentionsRead(?Task $task = null): void
    {
        $this->mentionedComments()->newPivotStatement()
            ->where('user_id', $this->getKey())
            ->whereNull('read_at')
            ->when($task, fn ($query) => $query->whereIn('task_comment_id', $task->comments()->reorder()->select('id')))
            ->update(['read_at' => now()]);
    }

    /**
     * プロジェクトを自分のグループごとに分ける（キーはグループ名）
     *
     * どのプロジェクトをどのグループに入れているかは project_user（人とプロジェクトの組）に
     * 入っているので、$projects は自分のリレーション越しに取ったものを渡す。
     *
     * @param  EloquentCollection<int, Project>  $projects
     * @param  EloquentCollection<int, ProjectGroup>  $groups
     * @return Collection<string, Collection<int, Project>>
     */
    public function projectSections(EloquentCollection $projects, EloquentCollection $groups): Collection
    {
        $byGroupId = $projects->groupBy(fn (Project $project) => (string) ($project->pivot->group_id ?? ''))->toBase();

        // セクションはグループ側を起点に組み立てる。
        // プロジェクトを集約して作ると、まだ何も入れていないグループが消えてしまう。
        // 並びはグループ管理で決めた順（position）で、中はプロジェクト名順のまま。
        $sections = $groups->mapWithKeys(
            fn (ProjectGroup $group) => [$group->name => $byGroupId->get((string) $group->id, collect())],
        )->toBase();

        // 未所属は最後に「グループなし」（キーは ''）として置く。1件もなければ出さない
        $ungrouped = $byGroupId->get('', collect());

        if ($ungrouped->isNotEmpty()) {
            $sections->put('', $ungrouped);
        }

        return $sections;
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

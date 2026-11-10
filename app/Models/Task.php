<?php

namespace App\Models;

use App\Enums\TaskActivity;
use App\Enums\TaskStatus;
use App\Events\NotificationReceived;
use App\Models\Concerns\Archivable;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\HtmlString;

#[Fillable(['title', 'description', 'task_list_id', 'position', 'status', 'started_on', 'ended_on'])]
class Task extends Model
{
    use Archivable;

    /**
     * 作った直後は未着手（DB の既定値はモデルへ読み戻されないので、ここにも書く）
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'todo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
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
     * @return BelongsTo<TaskList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'task_list_id');
    }

    /**
     * 作成者（削除済みのユーザーでも名前を出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * 最後に中身を変えた人（削除済みのユーザーでも名前を出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /**
     * 担当者
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('users.id');
    }

    /**
     * フォローしている人
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_follows')->withTimestamps();
    }

    /**
     * @return HasMany<TaskNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(TaskNotification::class);
    }

    /**
     * タスクの動きを記録する
     *
     * 最終更新者と更新日時を変え（コメントはタスク自体の変更ではないので変えない）、フォローしている人へ知らせる。
     *
     * @param  array<int, int>  $except  別の通知（メンションなど）で知らせた人
     */
    public function recordActivity(User $actor, TaskActivity $type, ?string $detail = null, array $except = []): void
    {
        if ($type !== TaskActivity::Comment) {
            // 並べ替えで更新日時を止めていたものも、ここでは必ず変える
            $this->timestamps = true;
            $this->updated_by = $actor->getKey();
            $this->touch();
        }

        $this->notifyFollowers($actor, $type, $detail, $except);
    }

    /**
     * フォローしている人へ動きを知らせる
     *
     * 動かした本人と、今プロジェクトに参加していない人には送らない。
     * コメント以外は、同じ人の同じ種類の動きが未読で残っていれば1件にまとめて新しくする。
     *
     * @param  array<int, int>  $except  別の通知（メンションなど）で知らせた人
     */
    public function notifyFollowers(User $actor, TaskActivity $type, ?string $detail = null, array $except = []): void
    {
        $recipients = $this->followers()
            ->whereKeyNot([$actor->getKey(), ...$except])
            ->whereHas('projects', fn ($projects) => $projects->whereKey($this->project_id))
            ->pluck('users.id');

        $detail = $detail === null ? null : mb_strimwidth($detail, 0, 255, '…');

        foreach ($recipients as $userId) {
            $notification = $type->merges()
                ? $this->notifications()
                    ->where('user_id', $userId)
                    ->where('actor_id', $actor->getKey())
                    ->where('type', $type)
                    ->whereNull('read_at')
                    ->first()
                : null;

            $notification ??= $this->notifications()->make();
            $notification->user_id = $userId;
            $notification->actor_id = $actor->getKey();
            $notification->type = $type;
            $notification->detail = $detail;
            $notification->created_at = now();
            $notification->save();

            NotificationReceived::notify($userId);
        }
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)->withTimestamps()->orderBy('labels.position');
    }

    /**
     * @return HasMany<TaskChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('position');
    }

    /**
     * すべてのチェックリストの項目（カードの件数用）
     *
     * @return HasManyThrough<TaskChecklistItem, TaskChecklist, $this>
     */
    public function checklistItems(): HasManyThrough
    {
        return $this->hasManyThrough(TaskChecklistItem::class, TaskChecklist::class);
    }

    /**
     * 説明は書式付き（RichText）。保存するときに許可した書式だけに整え、中身がなければ null にする
     */
    protected function description(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => RichText::sanitize($value) ?: null);
    }

    public function descriptionHtml(): HtmlString
    {
        return new HtmlString(RichText::render($this->description));
    }

    /**
     * 書式を外した説明（カードの抜粋など）
     */
    public function descriptionText(): string
    {
        return RichText::toPlainText($this->description);
    }

    /**
     * 新しいものから
     *
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest()->latest('id');
    }

    /**
     * 複製先へ担当者・ラベル・チェックリストを写す（コメントは写さない）
     */
    public function copyDetailsTo(self $copy): void
    {
        $copy->assignees()->sync($this->assignees()->pluck('users.id'));
        $copy->labels()->sync($this->labels()->pluck('labels.id'));

        foreach ($this->checklists as $checklist) {
            $checklistCopy = $checklist->replicate();
            $checklistCopy->task_id = $copy->getKey();
            $checklistCopy->save();

            foreach ($checklist->items as $item) {
                $itemCopy = $item->replicate();
                $itemCopy->task_checklist_id = $checklistCopy->getKey();
                $itemCopy->save();
            }
        }
    }

    /**
     * 移動先のプロジェクトに参加していない担当者とフォローを外す（チェックリストの項目の担当者も）
     */
    public function keepAssigneesIn(Project $project): void
    {
        $participants = $project->users()->pluck('users.id');

        $this->assignees()->detach($this->assignees()->pluck('users.id')->diff($participants));
        $this->followers()->detach($this->followers()->pluck('users.id')->diff($participants));

        TaskChecklistItem::query()
            ->whereIn('task_checklist_id', $this->checklists()->select('id'))
            ->whereNotIn('assignee_id', $participants)
            ->update(['assignee_id' => null]);
    }

    /**
     * ボードに出ているか（タスク自身かリストがアーカイブされていると出ない）
     */
    public function isOnBoard(): bool
    {
        return ! $this->isArchived() && ! $this->list->isArchived();
    }
}

<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
     * 担当者
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('users.id');
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
     * 新しいものから
     *
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest()->latest('id');
    }

    /**
     * 複製先へ担当者とチェックリストを写す（コメントは写さない）
     */
    public function copyDetailsTo(self $copy): void
    {
        $copy->assignees()->sync($this->assignees()->pluck('users.id'));

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
     * 移動先のプロジェクトに参加していない担当者を外す
     */
    public function keepAssigneesIn(Project $project): void
    {
        $this->assignees()->detach(
            $this->assignees()->pluck('users.id')->diff($project->users()->pluck('users.id')),
        );
    }

    /**
     * ボードに出ているか（タスク自身かリストがアーカイブされていると出ない）
     */
    public function isOnBoard(): bool
    {
        return ! $this->isArchived() && ! $this->list->isArchived();
    }
}

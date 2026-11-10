<?php

namespace App\Models;

use App\Enums\Reaction;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

#[Fillable(['body'])]
class TaskComment extends Model
{
    /**
     * 本文は書式付き（RichText）。保存するときに許可した書式だけに整える
     */
    protected function body(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => RichText::sanitize($value));
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * 投稿した人（削除済みのユーザーの投稿も名前を出す）
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * メンションされた人（削除済みのユーザーも表示には残す）
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_comment_mentions')->withTimestamps()->withTrashed();
    }

    /**
     * @return HasMany<TaskCommentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskCommentAttachment::class)->oldest('id');
    }

    /**
     * 添付ファイルの中身も一緒に消す（行は外部キーの cascade で消える）
     */
    protected static function booted(): void
    {
        static::deleting(fn (self $comment) => $comment->attachments->each->deleteFile());
    }

    /**
     * @return HasMany<TaskCommentReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(TaskCommentReaction::class)->oldest('id');
    }

    /**
     * 付いているリアクションを絵文字ごとにまとめる（Reaction の順。誰も付けていない絵文字は出さない）
     *
     * @return Collection<int, array{emoji: Reaction, users: Collection<int, User>, mine: bool}>
     */
    public function reactionSummary(?User $viewer): Collection
    {
        $byEmoji = $this->reactions->groupBy(fn (TaskCommentReaction $reaction) => $reaction->emoji->value);

        return collect(Reaction::cases())
            ->filter(fn (Reaction $emoji) => $byEmoji->has($emoji->value))
            ->map(fn (Reaction $emoji) => [
                'emoji' => $emoji,
                'users' => $users = $byEmoji[$emoji->value]->map->user,
                'mine' => $viewer !== null && $users->contains('id', $viewer->getKey()),
            ])
            ->values();
    }

    /**
     * 本文の「@名前」からメンションを決める
     *
     * 渡した人（プロジェクトの参加者）の名前だけを探すので、それ以外の人はメンションできない。
     * 「@田中太郎」が「田中」へのメンションにならないよう、長い名前から探して取り除いていく。
     *
     * @param  Collection<int, User>  $participants
     */
    public function syncMentions(Collection $participants): void
    {
        $body = $this->bodyText();
        $ids = [];

        foreach ($participants->groupBy('name')->sortByDesc(fn ($users, $name) => mb_strlen($name)) as $name => $users) {
            if (str_contains($body, "@{$name}")) {
                $ids = [...$ids, ...$users->modelKeys()];
                $body = str_replace("@{$name}", '', $body);
            }
        }

        $this->mentions()->sync($ids);
    }

    /**
     * 本文を HTML にする。書式を組み立て直しながら、メンションの部分だけ色を付ける
     */
    public function bodyHtml(): HtmlString
    {
        $tags = $this->mentions->mapWithKeys(fn (User $user) => [
            e("@{$user->name}") => '<span class="rounded bg-sky-50 px-0.5 text-sky-600">'.e("@{$user->name}").'</span>',
        ]);

        return new HtmlString(RichText::render($this->body, $tags->all()));
    }

    /**
     * 投稿したあとに本文を直したか（リアクションやメンションでは updated_at は変わらない）
     */
    public function isEdited(): bool
    {
        return $this->updated_at->gt($this->created_at);
    }

    /**
     * 書式を外した本文（通知の一覧、メンションの判定）
     */
    public function bodyText(): string
    {
        return RichText::toPlainText($this->body);
    }
}

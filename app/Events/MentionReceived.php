<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * メンションされたことを本人の画面へ知らせる
 *
 * 中身は送らない。受け取った画面が通知を取り直す。
 */
class MentionReceived implements ShouldBroadcastNow
{
    public function __construct(public int $userId) {}

    /**
     * 通知に失敗してもコメントは保存できているので、操作自体は失敗させない
     */
    public static function notify(int $userId): void
    {
        rescue(fn () => event(new self($userId)));
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'mention.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}

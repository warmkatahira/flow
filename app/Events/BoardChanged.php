<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * プロジェクトのボードが変わったことを、同じ画面を見ている人へ知らせる
 *
 * 中身は送らない。受け取った画面がボードを取り直すので、サーバーの状態とずれない。
 * キューを介すとワーカーが要るうえ反映も遅れるので、その場で送る。
 */
class BoardChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public int $projectId, public bool $reload = false) {}

    /**
     * 変更を知らせる（操作した本人の画面は除く）
     *
     * 通知に失敗しても保存は成功しているので、操作自体は失敗させない。
     *
     * @param  bool  $reload  ボード以外も変わるので、画面ごと読み込み直してほしいとき
     */
    public static function notify(Project $project, bool $reload = false): void
    {
        rescue(fn () => event((new self($project->getKey(), $reload))->dontBroadcastToCurrentUser()));
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("projects.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    /**
     * @return array<string, bool>
     */
    public function broadcastWith(): array
    {
        return ['reload' => $this->reload];
    }
}

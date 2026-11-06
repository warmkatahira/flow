<?php

namespace Tests\Feature\Project;

use App\Broadcasting\ProjectChannel;
use App\Enums\ListColor;
use App\Events\BoardChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private TaskList $list;

    private Task $task;

    private Dispatcher $realEvents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->ownedBy($this->user)->create();
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');

        $this->realEvents = Event::getFacadeRoot();

        Event::fake([BoardChanged::class]);
    }

    private function assertNotified(bool $reload = false): void
    {
        Event::assertDispatched(
            BoardChanged::class,
            fn (BoardChanged $event) => $event->projectId === $this->project->id && $event->reload === $reload,
        );
    }

    public function test_タスクの操作で通知される(): void
    {
        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => '追加']);
        Event::assertDispatchedTimes(BoardChanged::class, 1);

        $this->actingAs($this->user)->put(route('tasks.update', $this->task), ['title' => '変更']);
        Event::assertDispatchedTimes(BoardChanged::class, 2);

        $this->actingAs($this->user)->postJson(route('tasks.reorder', $this->project), ['list_id' => $this->list->id, 'ids' => [$this->task->id]]);
        Event::assertDispatchedTimes(BoardChanged::class, 3);

        $this->actingAs($this->user)->post(route('tasks.archive', $this->task));
        Event::assertDispatchedTimes(BoardChanged::class, 4);

        $this->actingAs($this->user)->post(route('tasks.unarchive', $this->task));
        Event::assertDispatchedTimes(BoardChanged::class, 5);

        $this->actingAs($this->user)->postJson(route('tasks.archive-many', $this->project), ['ids' => [$this->task->id]]);
        Event::assertDispatchedTimes(BoardChanged::class, 6);

        $this->actingAs($this->user)->delete(route('tasks.destroy', $this->task));
        Event::assertDispatchedTimes(BoardChanged::class, 7);

        $this->assertNotified();
    }

    public function test_リストの操作で通知される(): void
    {
        $this->actingAs($this->user)->post(route('lists.store', $this->project), ['name' => '追加']);
        Event::assertDispatchedTimes(BoardChanged::class, 1);

        $this->actingAs($this->user)->put(route('lists.update', $this->list), ['name' => '変更', 'color' => ListColor::Sky->value]);
        Event::assertDispatchedTimes(BoardChanged::class, 2);

        $this->actingAs($this->user)->postJson(route('lists.reorder', $this->project), ['ids' => [$this->list->id]]);
        Event::assertDispatchedTimes(BoardChanged::class, 3);

        $this->actingAs($this->user)->post(route('lists.archive', $this->list));
        Event::assertDispatchedTimes(BoardChanged::class, 4);

        $this->actingAs($this->user)->post(route('lists.unarchive', $this->list));
        Event::assertDispatchedTimes(BoardChanged::class, 5);

        $this->list->archive();
        $this->actingAs($this->user)->delete(route('lists.destroy', $this->list));
        Event::assertDispatchedTimes(BoardChanged::class, 6);
    }

    public function test_プロジェクトのアーカイブは画面ごと読み込み直させる(): void
    {
        $this->actingAs($this->user)->post(route('projects.archive', $this->project));

        $this->assertNotified(reload: true);
    }

    public function test_拒否された操作では通知しない(): void
    {
        $this->actingAs(User::factory()->create())->post(route('tasks.store', $this->list), ['title' => '不正']);

        Event::assertNotDispatched(BoardChanged::class);
    }

    public function test_通知に失敗しても操作は成功する(): void
    {
        // フェイクを外して、実際の配信で例外が起きる状態にする
        Event::swap($this->realEvents);

        $this->mock(BroadcastFactory::class, function (MockInterface $broadcast) {
            $broadcast->shouldReceive('socket')->andReturnNull();
            $broadcast->shouldReceive('queue')->once()->andThrow(new RuntimeException('Reverb が止まっている'));
        });

        $this->actingAs($this->user)
            ->put(route('tasks.update', $this->task), ['title' => '保存される'])
            ->assertOk();

        $this->assertSame('保存される', $this->task->fresh()->title);
    }

    public function test_チャンネルは参加者だけが購読できる(): void
    {
        $channel = new ProjectChannel;

        $this->assertTrue($channel->join($this->user, $this->project));
        $this->assertFalse($channel->join(User::factory()->create(), $this->project));
        $this->assertFalse($channel->join(User::factory()->systemAdmin()->create(), $this->project));
    }

    public function test_無効なユーザーや変更待ちのユーザーは購読できない(): void
    {
        $channel = new ProjectChannel;

        $this->user->is_active = false;
        $this->assertFalse($channel->join($this->user, $this->project));

        $this->user->is_active = true;
        $this->user->must_change_password = true;
        $this->assertFalse($channel->join($this->user, $this->project));
    }

    public function test_ボードの取り直しで最新の内容が返る(): void
    {
        $archived = Task::firstWhere('title', 'タスク');
        $archived->archive();

        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => '新しいタスク']);

        $this->actingAs($this->user)
            ->getJson(route('projects.board', $this->project))
            ->assertOk()
            ->assertJsonPath('archiveCount', 1)
            ->assertJson(fn ($json) => $json
                ->where('board', fn ($html) => str_contains($html, '新しいタスク') && ! str_contains($html, '>タスク<'))
                ->where('archive', fn ($html) => str_contains($html, 'タスク'))
                ->etc());
    }

    public function test_参加していない人はボードを取り直せない(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())
            ->getJson(route('projects.board', $this->project))
            ->assertNotFound();
    }

    public function test_アーカイブ中のボードは読み取り専用で返る(): void
    {
        $this->project->archive();

        $this->actingAs($this->user)
            ->getJson(route('projects.board', $this->project))
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('board', fn ($html) => ! str_contains($html, 'タスクを追加'))->etc());
    }
}

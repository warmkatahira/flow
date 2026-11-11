<?php

namespace Tests\Feature;

use App\Broadcasting\UserChannel;
use App\Enums\ListColor;
use App\Events\NotificationReceived;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $member;

    private Project $project;

    private TaskList $list;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => '投稿者']);
        $this->member = User::factory()->create(['name' => '参加者']);
        $this->project = Project::factory()->ownedBy($this->author)->create(['name' => '案件']);
        $this->project->users()->attach($this->member, ['role' => 'member']);
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->author)->post(route('tasks.store', $this->list), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');
    }

    private function comment(string $body, ?User $as = null): TaskComment
    {
        $this->actingAs($as ?? $this->author)->post(route('tasks.comments.store', $this->task), ['body' => $body]);

        return TaskComment::query()->latest('id')->first();
    }

    public function test_メンションされると未読が増える(): void
    {
        $this->comment('@参加者 確認して');

        $this->assertSame(1, $this->member->unreadMentionCount());

        $response = $this->actingAs($this->member)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertStringContainsString('投稿者 さんがメンションしました', $response->json('html'));
    }

    public function test_メンションされた人へ通知が届く(): void
    {
        Event::fake([NotificationReceived::class]);

        $this->comment('@参加者 確認して');

        Event::assertDispatched(NotificationReceived::class, fn (NotificationReceived $event) => $event->userId === $this->member->id);
    }

    public function test_自分へのメンションは通知にならない(): void
    {
        Event::fake([NotificationReceived::class]);

        $this->comment('@投稿者 メモ');

        Event::assertNotDispatched(NotificationReceived::class);
        $this->assertSame(0, $this->author->unreadMentionCount());
    }

    public function test_通知からタスクへ移れる(): void
    {
        $comment = $this->comment('@参加者 確認して');

        $this->actingAs($this->member)->get(route('notifications.open', $comment))
            ->assertRedirect(route('projects.show', [$this->project, 'task' => $this->task->id]));
    }

    public function test_タスクを開いた分だけ未読が減る(): void
    {
        $this->comment('@参加者 1件目');
        $this->comment('@参加者 2件目');

        $this->actingAs($this->author)->post(route('tasks.store', $this->list), ['title' => '別のタスク']);
        $other = Task::firstWhere('title', '別のタスク');
        $this->actingAs($this->author)->post(route('tasks.comments.store', $other), ['body' => '@参加者 別の件']);

        $this->assertSame(3, $this->member->unreadMentionCount());

        $this->actingAs($this->member)->get(route('tasks.show', $this->task))->assertOk();

        $this->assertSame(1, $this->member->unreadMentionCount());
    }

    public function test_既読の通知にはバッジが付く(): void
    {
        $this->comment('@参加者 確認して');

        $unread = $this->actingAs($this->member)->getJson(route('notifications.index'))->json('html');
        $this->assertStringNotContainsString('既読', $unread);

        $this->member->markMentionsRead();

        $read = $this->actingAs($this->member)->getJson(route('notifications.index'))->json('html');
        $this->assertStringContainsString('既読', $read);
    }

    public function test_他の人宛ての通知は開けない(): void
    {
        $comment = $this->comment('@参加者 確認して');

        $this->actingAs(User::factory()->create())->get(route('notifications.open', $comment))->assertNotFound();

        $this->assertSame(1, $this->member->unreadMentionCount());
    }

    public function test_すべて既読にできる(): void
    {
        $this->comment('@参加者 1件目');
        $this->comment('@参加者 2件目');

        $this->actingAs($this->member)->postJson(route('notifications.read-all'))->assertOk()->assertJsonPath('count', 0);

        $this->assertSame(0, $this->member->unreadMentionCount());
    }

    public function test_すべて既読は他の人の未読を変えない(): void
    {
        $other = User::factory()->create(['name' => '別の人']);
        $this->project->users()->attach($other, ['role' => 'member']);
        $this->comment('@参加者 @別の人 確認して');

        $this->actingAs($this->member)->postJson(route('notifications.read-all'));

        $this->assertSame(1, $other->unreadMentionCount());
    }

    public function test_見られなくなったタスクの通知は出ない(): void
    {
        $this->comment('@参加者 確認して');

        $this->task->archive();
        $this->assertSame(0, $this->member->unreadMentionCount());

        $this->task->unarchive();
        $this->project->users()->detach($this->member);
        $this->assertSame(0, $this->member->unreadMentionCount());
    }

    public function test_ベルに未読の件数が出る(): void
    {
        $this->comment('@参加者 確認して');

        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('notifications(1, ', false)
            ->assertSee(', '.$this->member->id.', ', false);
    }

    public function test_本人だけが自分のチャンネルを購読できる(): void
    {
        $channel = new UserChannel;

        $this->assertTrue($channel->join($this->member, (string) $this->member->id));
        $this->assertFalse($channel->join($this->member, (string) $this->author->id));
    }
}

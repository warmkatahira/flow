<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Enums\TaskActivity;
use App\Events\NotificationReceived;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskFollowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $follower;

    private Project $project;

    private TaskList $todo;

    private TaskList $doing;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => '持ち主']);
        $this->follower = User::factory()->create(['name' => 'フォロワー']);
        $this->project = Project::factory()->ownedBy($this->owner)->create(['name' => '案件']);
        $this->project->users()->attach($this->follower, ['role' => 'member']);
        $this->todo = $this->project->taskLists()->create(['name' => '開始前', 'color' => ListColor::Sky->value, 'position' => 0]);
        $this->doing = $this->project->taskLists()->create(['name' => '進行中', 'color' => ListColor::Sky->value, 'position' => 1]);

        $this->actingAs($this->owner)->post(route('tasks.store', $this->todo), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');

        $this->actingAs($this->follower)->post(route('tasks.follow', $this->task))->assertOk();
    }

    /**
     * @return \Illuminate\Support\Collection<int, TaskNotification>
     */
    private function notifications(): \Illuminate\Support\Collection
    {
        return $this->follower->taskNotifications()->get();
    }

    public function test_フォローを付け外しできる(): void
    {
        $this->assertTrue($this->task->followers()->whereKey($this->follower->id)->exists());

        $this->actingAs($this->follower)->get(route('tasks.show', $this->task))->assertSee('フォロー中');

        $this->actingAs($this->follower)->post(route('tasks.follow', $this->task))->assertSee('フォロー');

        $this->assertFalse($this->task->followers()->whereKey($this->follower->id)->exists());
    }

    public function test_参加していないプロジェクトのタスクはフォローできない(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('tasks.follow', $this->task))->assertNotFound();
    }

    public function test_ボードのカードにフォローの印と絞り込み用の値が付く(): void
    {
        $this->actingAs($this->follower)->get(route('projects.show', $this->project))
            ->assertSee('data-followed="1"', false)
            ->assertSee('title="フォロー中"', false);

        $this->actingAs($this->owner)->get(route('projects.show', $this->project))
            ->assertDontSee('data-followed="1"', false);
    }

    public function test_ステータスを変えるとフォローしている人へ通知が残る(): void
    {
        Event::fake([NotificationReceived::class]);

        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'done']);

        $notification = $this->notifications()->sole();
        $this->assertSame(TaskActivity::Status, $notification->type);
        $this->assertSame('ステータスを「完了」にしました', $notification->message());
        $this->assertSame(1, $this->follower->unreadNotificationCount());
        Event::assertDispatched(NotificationReceived::class, fn ($event) => $event->userId === $this->follower->id);
    }

    public function test_自分の操作は自分に通知しない(): void
    {
        $this->actingAs($this->follower)->put(route('tasks.status', $this->task), ['status' => 'done']);

        $this->assertCount(0, $this->notifications());
    }

    public function test_変わっていなければ通知しない(): void
    {
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'todo']);
        $this->actingAs($this->owner)->put(route('tasks.update', $this->task), ['title' => 'タスク']);

        $this->assertCount(0, $this->notifications());
    }

    public function test_同じ人の同じ種類の動きは未読のうちは1件にまとめる(): void
    {
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'doing']);
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'done']);

        $this->assertSame('ステータスを「完了」にしました', $this->notifications()->sole()->message());

        $this->follower->markNotificationsRead();
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'doing']);

        $this->assertCount(2, $this->notifications());
    }

    public function test_コメントは1件ずつ残し_メンションした人には二重に送らない(): void
    {
        $this->actingAs($this->owner)->post(route('tasks.comments.store', $this->task), ['body' => '一つ目']);
        $this->actingAs($this->owner)->post(route('tasks.comments.store', $this->task), ['body' => '二つ目']);

        $this->assertSame(['二つ目', '一つ目'], $this->follower->taskNotifications()->latest('id')->pluck('detail')->all());

        $this->actingAs($this->owner)->post(route('tasks.comments.store', $this->task), ['body' => '@フォロワー 三つ目']);

        $this->assertCount(2, $this->notifications());
        $this->assertSame(1, $this->follower->unreadMentionCount());
        $this->assertSame(3, $this->follower->unreadNotificationCount());
    }

    public function test_いろいろな動きを通知する(): void
    {
        $owner = $this->actingAs($this->owner);

        $owner->put(route('tasks.update', $this->task), ['title' => '新しい名前']);
        $owner->put(route('tasks.update', $this->task), ['description' => '<p>説明</p>']);
        $owner->put(route('tasks.period', $this->task), ['started_on' => '2026-11-10', 'ended_on' => '2026-11-20']);
        $owner->post(route('tasks.assignee', $this->task), ['user_id' => $this->owner->id]);
        $label = $this->project->labels()->make(['name' => '重要', 'color' => ListColor::Sky->value]);
        $label->position = 0;
        $label->save();
        $owner->put(route('tasks.labels.sync', $this->task), ['labels' => [$label->id]]);
        $owner->post(route('tasks.checklists.store', $this->task), ['title' => '手順']);
        $owner->post(route('tasks.reorder', $this->project), ['list_id' => $this->doing->id, 'ids' => [$this->task->id], 'moved' => [$this->task->id]]);

        $messages = $this->notifications()->map->message()->all();

        $this->assertEqualsCanonicalizing([
            'タイトルを変更しました',
            '説明を変更しました',
            '期間を 11/10〜11/20 にしました',
            '担当者を変更しました',
            'ラベルを変更しました',
            'チェックリストを変更しました',
            '「進行中」へ移動しました',
        ], $messages);
    }

    public function test_同じリストの中の並べ替えは通知しない(): void
    {
        $this->actingAs($this->owner)->post(route('tasks.reorder', $this->project), ['list_id' => $this->todo->id, 'ids' => [$this->task->id], 'moved' => [$this->task->id]]);

        $this->assertCount(0, $this->notifications());
    }

    public function test_アーカイブも通知し_開くとアーカイブの画面へ案内する(): void
    {
        $this->actingAs($this->owner)->post(route('tasks.archive', $this->task));

        $notification = $this->notifications()->sole();
        $this->assertSame(TaskActivity::Archived, $notification->type);

        $this->actingAs($this->follower)->get(route('notifications.activities.open', $notification))
            ->assertRedirect(route('projects.show', [$this->project, 'tab' => 'archive']));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_通知から開くとタスクが開き_開いたら既読になる(): void
    {
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'done']);
        $notification = $this->notifications()->sole();

        $this->actingAs($this->follower)->get(route('notifications.activities.open', $notification))
            ->assertRedirect(route('projects.show', [$this->project, 'task' => $this->task->id]));

        $this->actingAs($this->follower)->get(route('tasks.show', $this->task));

        $this->assertSame(0, $this->follower->unreadNotificationCount());
    }

    public function test_他の人の通知は開けない(): void
    {
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'done']);

        $this->actingAs($this->owner)->get(route('notifications.activities.open', $this->notifications()->sole()))->assertNotFound();
    }

    public function test_ベルにメンションとフォローの通知を混ぜて出す(): void
    {
        $this->actingAs($this->owner)->put(route('tasks.status', $this->task), ['status' => 'done']);
        $this->actingAs($this->owner)->post(route('tasks.comments.store', $this->task), ['body' => '@フォロワー 見て']);

        $response = $this->actingAs($this->follower)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('count', 2);

        $html = $response->json('html');
        $this->assertStringContainsString('持ち主 さんがメンションしました', $html);
        $this->assertStringContainsString('持ち主 さんがステータスを「完了」にしました', $html);

        $this->actingAs($this->follower)->postJson(route('notifications.read-all'))->assertJsonPath('count', 0);
    }

    public function test_参加者から外れるとフォローも外れて通知されない(): void
    {
        $this->actingAs($this->owner)->put(route('projects.update', $this->project), [
            'name' => '案件',
            'members' => [$this->owner->id],
            'roles' => [$this->owner->id => 'owner'],
        ]);

        $this->assertFalse($this->task->followers()->exists());
    }

    public function test_参加していないプロジェクトへ移すとフォローが外れる(): void
    {
        $other = Project::factory()->ownedBy($this->owner)->create();
        $list = $other->taskLists()->create(['name' => '別', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->owner)->post(route('tasks.move', $this->task), ['list_id' => $list->id]);

        $this->assertFalse($this->task->followers()->exists());
        $this->assertCount(0, TaskNotification::all());
    }

    public function test_作成者と最終更新者を記録する(): void
    {
        $this->assertSame($this->owner->id, $this->task->updated_by);

        $this->travel(1)->hours();
        $this->actingAs($this->follower)->put(route('tasks.status', $this->task), ['status' => 'done']);

        $task = $this->task->fresh();
        $this->assertSame($this->owner->id, $task->created_by);
        $this->assertSame($this->follower->id, $task->updated_by);
        $this->assertTrue($task->updated_at->isAfter($task->created_at));

        $this->actingAs($this->owner)->get(route('tasks.show', $this->task))
            ->assertSeeInOrder(['作成', '持ち主', '最終更新', 'フォロワー']);
    }

    public function test_チェックリストやラベルの変更でも最終更新者が変わる(): void
    {
        $this->actingAs($this->follower)->post(route('tasks.checklists.store', $this->task), ['title' => '手順']);

        $this->assertSame($this->follower->id, $this->task->fresh()->updated_by);
    }

    public function test_並べ替えとコメントでは最終更新は変わらない(): void
    {
        $before = $this->task->fresh()->updated_at;
        $this->travel(1)->hours();

        $this->actingAs($this->follower)->post(route('tasks.reorder', $this->project), ['list_id' => $this->todo->id, 'ids' => [$this->task->id]]);
        $this->actingAs($this->follower)->post(route('tasks.comments.store', $this->task), ['body' => 'メモ']);

        $task = $this->task->fresh();
        $this->assertSame($this->owner->id, $task->updated_by);
        $this->assertTrue($task->updated_at->equalTo($before));
    }

    public function test_リストを移ると最終更新者が変わる(): void
    {
        $this->travel(1)->hours();

        $this->actingAs($this->follower)->post(route('tasks.reorder', $this->project), ['list_id' => $this->doing->id, 'ids' => [$this->task->id], 'moved' => [$this->task->id]]);

        $task = $this->task->fresh();
        $this->assertSame($this->doing->id, $task->task_list_id);
        $this->assertSame($this->follower->id, $task->updated_by);
        $this->assertTrue($task->updated_at->isAfter($task->created_at));
    }
}

<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private TaskList $list;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '作った人']);
        $this->project = Project::factory()->ownedBy($this->user)->create();
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');
    }

    private function participant(): User
    {
        $user = User::factory()->create();
        $this->project->users()->attach($user, ['role' => 'member']);

        return $user;
    }

    public function test_作ったタスクは未着手で作成者が入る(): void
    {
        $this->assertSame(TaskStatus::Todo, $this->task->refresh()->status);
        $this->assertTrue($this->user->is($this->task->creator));
    }

    public function test_詳細を開ける(): void
    {
        $this->actingAs($this->user)->get(route('tasks.show', $this->task))
            ->assertOk()
            ->assertSee('#'.$this->task->number.' ToDo')
            ->assertSee('説明を編集')
            ->assertSee('作成者: 作った人');
    }

    public function test_参加していない人は開けない(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create())->get(route('tasks.show', $this->task))->assertNotFound();
    }

    public function test_アーカイブしたタスクは開けない(): void
    {
        $this->task->archive();

        $this->actingAs($this->user)->get(route('tasks.show', $this->task))->assertNotFound();
    }

    public function test_ステータスを変えられる(): void
    {
        $this->actingAs($this->user)->put(route('tasks.status', $this->task), ['status' => 'on_hold'])->assertOk();

        $this->assertSame(TaskStatus::OnHold, $this->task->refresh()->status);
    }

    public function test_決まったステータス以外は入らない(): void
    {
        $this->actingAs($this->user)->putJson(route('tasks.status', $this->task), ['status' => 'other'])->assertUnprocessable();
    }

    public function test_タイトルと説明は片方だけ変えられる(): void
    {
        $this->actingAs($this->user)->put(route('tasks.update', $this->task), ['description' => '説明'])->assertOk();
        $this->actingAs($this->user)->put(route('tasks.update', $this->task), ['title' => '新しい名前'])->assertOk();

        $this->task->refresh();
        $this->assertSame('新しい名前', $this->task->title);
        $this->assertSame('説明', $this->task->description);
    }

    public function test_期間を設定して消せる(): void
    {
        $this->actingAs($this->user)->put(route('tasks.period', $this->task), ['started_on' => '2026-11-01', 'ended_on' => '2026-11-10'])
            ->assertOk()
            ->assertSee('2026/11/01(日)');

        $this->assertSame('2026-11-10', $this->task->refresh()->ended_on->format('Y-m-d'));

        $this->actingAs($this->user)->put(route('tasks.period', $this->task), ['started_on' => null, 'ended_on' => null])->assertOk();

        $this->assertNull($this->task->refresh()->started_on);
    }

    public function test_終了日だけでもよいが開始日より前にはできない(): void
    {
        $this->actingAs($this->user)->putJson(route('tasks.period', $this->task), ['ended_on' => '2026-11-10'])->assertOk();

        $this->actingAs($this->user)
            ->putJson(route('tasks.period', $this->task), ['started_on' => '2026-11-11', 'ended_on' => '2026-11-10'])
            ->assertJsonValidationErrors('ended_on');
    }

    public function test_担当者を付け外しできる(): void
    {
        $member = $this->participant();

        $this->actingAs($this->user)->post(route('tasks.assignee', $this->task), ['user_id' => $member->id])->assertOk();
        $this->assertTrue($this->task->assignees->contains($member));

        $this->actingAs($this->user)->post(route('tasks.assignee', $this->task), ['user_id' => $member->id])->assertOk();
        $this->assertFalse($this->task->fresh()->assignees->contains($member));
    }

    public function test_参加者以外は担当者にできない(): void
    {
        $this->actingAs($this->user)->post(route('tasks.assignee', $this->task), ['user_id' => User::factory()->create()->id])->assertNotFound();
    }

    public function test_参加者から外すと担当からも外れる(): void
    {
        $member = $this->participant();
        $this->task->assignees()->attach($member);

        $this->actingAs($this->user)->put(route('projects.update', $this->project), [
            'name' => $this->project->name,
            'members' => [$this->user->id],
            'roles' => [$this->user->id => 'owner'],
        ])->assertRedirect();

        $this->assertFalse($this->project->hasParticipant($member));
        $this->assertFalse($this->task->fresh()->assignees->contains($member));
    }

    public function test_他のプロジェクトへ動かすと参加していない担当者は外れる(): void
    {
        $stay = $this->user;
        $leave = $this->participant();
        $this->task->assignees()->attach([$stay->id, $leave->id]);

        $target = Project::factory()->ownedBy($this->user)->create();
        $list = $target->taskLists()->create(['name' => '先', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->user)->post(route('tasks.move', $this->task), ['list_id' => $list->id]);

        $this->assertSame([$stay->id], $this->task->fresh()->assignees->pluck('id')->all());
    }

    private function checklist(string $title = 'チェック'): TaskChecklist
    {
        $this->actingAs($this->user)->post(route('tasks.checklists.store', $this->task), ['title' => $title])->assertOk();

        return TaskChecklist::firstWhere('title', $title);
    }

    public function test_チェックリストを複数作って名前を変えて消せる(): void
    {
        $first = $this->checklist('一つ目');
        $second = $this->checklist('二つ目');

        $this->assertSame([1, 2], [$first->position, $second->position]);

        $this->actingAs($this->user)->put(route('checklists.update', $first), ['title' => '改名'])->assertOk()->assertSee('改名');

        $this->actingAs($this->user)->delete(route('checklists.destroy', $second))->assertOk();
        $this->assertModelMissing($second);
    }

    public function test_項目を追加して切り替えると進捗が変わる(): void
    {
        $checklist = $this->checklist();

        $this->actingAs($this->user)->post(route('checklists.items.store', $checklist), ['title' => '項目1'])->assertOk()->assertSee('0%');
        $this->actingAs($this->user)->post(route('checklists.items.store', $checklist), ['title' => '項目2'])->assertOk();

        $item = TaskChecklistItem::firstWhere('title', '項目1');

        $this->actingAs($this->user)->post(route('checklist-items.toggle', $item))->assertOk()->assertSee('50%');
        $this->assertTrue($item->refresh()->is_done);

        $this->actingAs($this->user)->delete(route('checklist-items.destroy', $item))->assertOk();
        $this->assertModelMissing($item);
    }

    public function test_チェックリストを消すと項目も消える(): void
    {
        $checklist = $this->checklist();
        $this->actingAs($this->user)->post(route('checklists.items.store', $checklist), ['title' => '項目']);

        $this->actingAs($this->user)->delete(route('checklists.destroy', $checklist))->assertOk();

        $this->assertDatabaseCount('task_checklist_items', 0);
    }

    public function test_参加していない人はチェックリストを触れない(): void
    {
        $checklist = $this->checklist();
        $this->actingAs($this->user)->post(route('checklists.items.store', $checklist), ['title' => '項目']);
        $item = TaskChecklistItem::first();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('checklist-items.toggle', $item))->assertNotFound();
        $this->actingAs($outsider)->post(route('checklists.items.store', $checklist), ['title' => '不正'])->assertNotFound();
        $this->actingAs($outsider)->delete(route('checklists.destroy', $checklist))->assertNotFound();

        $this->assertFalse($item->refresh()->is_done);
        $this->assertModelExists($checklist);
    }

    private function item(TaskChecklist $checklist, string $title): TaskChecklistItem
    {
        $this->actingAs($this->user)->post(route('checklists.items.store', $checklist), ['title' => $title]);

        return TaskChecklistItem::firstWhere('title', $title);
    }

    public function test_チェックリストを並べ替えられる(): void
    {
        $first = $this->checklist('一つ目');
        $second = $this->checklist('二つ目');

        $this->actingAs($this->user)
            ->postJson(route('tasks.checklists.reorder', $this->task), ['ids' => [$second->id, $first->id]])
            ->assertNoContent();

        $this->assertSame(['二つ目', '一つ目'], $this->task->checklists()->pluck('title')->all());
    }

    public function test_項目を並べ替えられる(): void
    {
        $checklist = $this->checklist();
        $first = $this->item($checklist, '一');
        $second = $this->item($checklist, '二');

        $this->actingAs($this->user)
            ->postJson(route('checklists.items.reorder', $checklist), ['ids' => [$second->id, $first->id], 'moved' => $second->id])
            ->assertNoContent();

        $this->assertSame(['二', '一'], $checklist->items()->pluck('title')->all());
    }

    public function test_項目を別の枠へ動かせる(): void
    {
        $from = $this->checklist('元');
        $to = $this->checklist('先');
        $moved = $this->item($from, '動かす');
        $stay = $this->item($to, '先客');

        $this->actingAs($this->user)
            ->postJson(route('checklists.items.reorder', $to), ['ids' => [$moved->id, $stay->id], 'moved' => $moved->id])
            ->assertNoContent();

        $this->assertSame(['動かす', '先客'], $to->items()->pluck('title')->all());
        $this->assertCount(0, $from->items);
    }

    public function test_moved以外の項目は枠を変えない(): void
    {
        $from = $this->checklist('元');
        $to = $this->checklist('先');
        $other = $this->item($from, '他の人が動かした');
        $moved = $this->item($from, '動かす');

        $this->actingAs($this->user)
            ->postJson(route('checklists.items.reorder', $to), ['ids' => [$other->id, $moved->id], 'moved' => $moved->id])
            ->assertNoContent();

        $this->assertSame($from->id, $other->refresh()->task_checklist_id);
        $this->assertSame($to->id, $moved->refresh()->task_checklist_id);
    }

    public function test_他のタスクの項目やチェックリストは動かせない(): void
    {
        $mine = $this->checklist('自分');
        $this->actingAs($this->user)->post(route('tasks.store', $this->list), ['title' => '別のタスク']);
        $other = Task::firstWhere('title', '別のタスク');
        $this->actingAs($this->user)->post(route('tasks.checklists.store', $other), ['title' => '別の枠']);
        $otherChecklist = TaskChecklist::firstWhere('title', '別の枠');
        $otherItem = $this->item($otherChecklist, '別の項目');

        $this->actingAs($this->user)
            ->postJson(route('checklists.items.reorder', $mine), ['ids' => [$otherItem->id], 'moved' => $otherItem->id])
            ->assertNoContent();
        $this->actingAs($this->user)
            ->postJson(route('tasks.checklists.reorder', $this->task), ['ids' => [$otherChecklist->id, $mine->id]])
            ->assertNoContent();

        $this->assertSame($otherChecklist->id, $otherItem->refresh()->task_checklist_id);
        $this->assertSame($other->id, $otherChecklist->refresh()->task_id);
        $this->assertSame(1, $otherChecklist->position);
    }

    public function test_参加していない人は並べ替えられない(): void
    {
        $checklist = $this->checklist();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson(route('tasks.checklists.reorder', $this->task), ['ids' => [$checklist->id]])->assertNotFound();
        $this->actingAs($outsider)->postJson(route('checklists.items.reorder', $checklist), ['ids' => [1]])->assertNotFound();
    }

    private function checkItem(string $title = '項目'): TaskChecklistItem
    {
        return $this->item(TaskChecklist::firstWhere('title', 'チェック') ?? $this->checklist(), $title);
    }

    public function test_項目に期間を設定できる(): void
    {
        $item = $this->checkItem();

        $this->actingAs($this->user)->put(route('checklist-items.period', $item), ['started_on' => '2026-11-07', 'ended_on' => '2026-11-09'])
            ->assertOk()
            ->assertSee('11/7(土) 〜 11/9(月)');

        $item->refresh();
        $this->assertSame('2026-11-07', $item->started_on->format('Y-m-d'));
        $this->assertSame('2026-11-09', $item->ended_on->format('Y-m-d'));

        // 空で送ると消える
        $this->actingAs($this->user)->put(route('checklist-items.period', $item), ['started_on' => '', 'ended_on' => ''])->assertOk();
        $this->assertNull($item->refresh()->started_on);
        $this->assertNull($item->ended_on);
    }

    public function test_項目の終了日は開始日より前にできない(): void
    {
        $item = $this->checkItem();

        $this->actingAs($this->user)->put(route('checklist-items.period', $item), ['started_on' => '2026-11-09', 'ended_on' => '2026-11-07'])
            ->assertSessionHasErrors('ended_on');
        $this->assertNull($item->refresh()->started_on);
    }

    public function test_項目に担当者を1人付けて替えて外せる(): void
    {
        $item = $this->checkItem();
        $member = $this->participant();

        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => $member->id])->assertOk();
        $this->assertSame($member->id, $item->refresh()->assignee_id);

        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => $this->user->id])->assertOk();
        $this->assertSame($this->user->id, $item->refresh()->assignee_id);

        // 今の担当者をもう一度選ぶと外れる
        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => $this->user->id])->assertOk();
        $this->assertNull($item->refresh()->assignee_id);

        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => $member->id]);
        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => ''])->assertOk();
        $this->assertNull($item->refresh()->assignee_id);
    }

    public function test_参加者以外は項目の担当者にできない(): void
    {
        $item = $this->checkItem();

        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => User::factory()->create()->id])->assertNotFound();
        $this->assertNull($item->refresh()->assignee_id);
    }

    public function test_参加していない人は項目の期間も担当者も変えられない(): void
    {
        $item = $this->checkItem();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->put(route('checklist-items.period', $item), ['started_on' => '2026-11-07'])->assertNotFound();
        $this->actingAs($outsider)->post(route('checklist-items.assignee', $item), ['user_id' => $outsider->id])->assertNotFound();
    }

    public function test_参加者から外すと項目の担当からも外れる(): void
    {
        $item = $this->checkItem();
        $member = $this->participant();
        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => $member->id]);

        $this->actingAs($this->user)->put(route('projects.update', $this->project), [
            'name' => $this->project->name,
            'members' => [$this->user->id],
            'roles' => [$this->user->id => 'owner'],
        ])->assertRedirect();

        $this->assertNull($item->refresh()->assignee_id);
    }

    public function test_他のプロジェクトへ動かすと参加していない項目の担当者は外れる(): void
    {
        $stay = $this->checkItem('残る');
        $leave = $this->checkItem('外れる');
        $member = $this->participant();
        $this->actingAs($this->user)->post(route('checklist-items.assignee', $stay), ['user_id' => $this->user->id]);
        $this->actingAs($this->user)->post(route('checklist-items.assignee', $leave), ['user_id' => $member->id]);

        $target = Project::factory()->ownedBy($this->user)->create();
        $list = $target->taskLists()->create(['name' => '先', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->user)->post(route('tasks.move', $this->task), ['list_id' => $list->id]);

        $this->assertSame($this->user->id, $stay->refresh()->assignee_id);
        $this->assertNull($leave->refresh()->assignee_id);
    }

    public function test_複製すると項目の期間と担当者も写る(): void
    {
        $item = $this->checkItem();
        $this->actingAs($this->user)->put(route('checklist-items.period', $item), ['started_on' => '2026-11-07', 'ended_on' => '2026-11-09']);
        $this->actingAs($this->user)->post(route('checklist-items.assignee', $item), ['user_id' => $this->user->id]);

        $this->actingAs($this->user)->post(route('tasks.duplicate', $this->task));

        $copy = Task::firstWhere('title', 'タスク のコピー')->checklistItems->first();
        $this->assertSame('2026-11-07', $copy->started_on->format('Y-m-d'));
        $this->assertSame($this->user->id, $copy->assignee_id);
    }

    public function test_コメントを投稿できる(): void
    {
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => '確認します'])
            ->assertOk()
            ->assertSee('確認します');

        $this->assertTrue($this->user->is(TaskComment::first()->user));
    }

    public function test_コメントを消せるのは投稿した本人だけ(): void
    {
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => 'コメント']);
        $comment = TaskComment::first();

        $this->actingAs($this->participant())->delete(route('comments.destroy', $comment))->assertForbidden();
        $this->assertModelExists($comment);

        $this->actingAs($this->user)->delete(route('comments.destroy', $comment))->assertOk();
        $this->assertModelMissing($comment);
    }

    public function test_参加者をメンションできる(): void
    {
        $member = $this->participant();
        $member->update(['name' => '田中太郎']);

        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => '@田中太郎 確認して'])
            ->assertOk()
            ->assertSee('<span class="rounded bg-sky-50 px-0.5 text-sky-600">@田中太郎</span>', false);

        $this->assertTrue(TaskComment::first()->mentions->contains($member));
    }

    public function test_参加者以外はメンションできない(): void
    {
        User::factory()->create(['name' => '部外者']);

        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => '@部外者 見て'])
            ->assertOk()
            ->assertDontSee('<span class="rounded bg-sky-50', false);

        $this->assertCount(0, TaskComment::first()->mentions);
    }

    public function test_名前の一部が同じ人を巻き込まない(): void
    {
        $short = $this->participant();
        $short->update(['name' => '田中']);
        $long = $this->participant();
        $long->update(['name' => '田中太郎']);

        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => '@田中太郎 確認して']);

        $this->assertSame([$long->id], TaskComment::first()->mentions->pluck('id')->all());
    }

    public function test_本文はエスケープされる(): void
    {
        $member = $this->participant();
        $member->update(['name' => '<b>名前</b>']);

        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => '<script>alert(1)</script> @<b>名前</b>'])
            ->assertOk()
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('@<b>', false);
    }

    public function test_削除済みのユーザーのコメントも読める(): void
    {
        $member = $this->participant();
        $this->actingAs($member)->post(route('tasks.comments.store', $this->task), ['body' => '退職前のコメント']);
        $member->delete();

        $this->actingAs($this->user)->get(route('tasks.show', $this->task))->assertOk()->assertSee('退職前のコメント');
    }

    public function test_コメントにリアクションを付け外しできる(): void
    {
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => 'コメント']);
        $comment = TaskComment::first();

        $this->actingAs($this->user)->post(route('comments.react', $comment), ['emoji' => '👍'])
            ->assertOk()
            ->assertSee('いいね 1件（自分も付けています）');
        $this->actingAs($this->participant())->post(route('comments.react', $comment), ['emoji' => '👍'])
            ->assertOk()
            ->assertSee('いいね 2件');
        $this->assertSame(2, $comment->reactions()->count());

        // もう一度押すと外れる
        $this->actingAs($this->user)->post(route('comments.react', $comment), ['emoji' => '👍'])
            ->assertOk()
            ->assertDontSee('（自分も付けています）');
        $this->assertSame(1, $comment->reactions()->count());
    }

    public function test_リアクションは1つのコメントに1人1つで別の絵文字は付け替える(): void
    {
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => 'コメント']);
        $comment = TaskComment::first();

        $this->actingAs($this->user)->post(route('comments.react', $comment), ['emoji' => '👍']);
        $this->actingAs($this->user)->post(route('comments.react', $comment), ['emoji' => '🎉'])
            ->assertOk()
            ->assertSee('お祝い 1件（自分も付けています）')
            ->assertDontSee('いいね 1件');

        $this->assertSame(['🎉'], $comment->reactions()->pluck('emoji')->map->value->all());
    }

    public function test_決まった絵文字以外は付けられない(): void
    {
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => 'コメント']);
        $comment = TaskComment::first();

        $this->actingAs($this->user)->post(route('comments.react', $comment), ['emoji' => '💩'])->assertSessionHasErrors('emoji');
        $this->assertSame(0, $comment->reactions()->count());
    }

    public function test_参加していない人はリアクションできない(): void
    {
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => 'コメント']);
        $comment = TaskComment::first();

        $this->actingAs(User::factory()->create())->post(route('comments.react', $comment), ['emoji' => '👍'])->assertNotFound();
        $this->assertSame(0, $comment->reactions()->count());
    }

    public function test_複製すると担当者とチェックリストは写りコメントは写らない(): void
    {
        $this->task->assignees()->attach($this->user);
        $this->actingAs($this->user)->post(route('checklists.items.store', $this->checklist()), ['title' => '項目']);
        $this->actingAs($this->user)->post(route('tasks.comments.store', $this->task), ['body' => 'コメント']);

        $this->actingAs($this->user)->post(route('tasks.duplicate', $this->task));

        $copy = Task::firstWhere('title', 'タスク のコピー');
        $this->assertTrue($copy->assignees->contains($this->user));
        $this->assertSame('チェック', $copy->checklists->first()->title);
        $this->assertCount(1, $copy->checklistItems);
        $this->assertCount(0, $copy->comments);
    }

    public function test_ボードのカードに状態が出る(): void
    {
        $this->task->update(['started_on' => '2026-11-01', 'ended_on' => '2026-11-10']);
        $this->actingAs($this->user)->post(route('checklists.items.store', $this->checklist()), ['title' => '項目']);

        $this->actingAs($this->user)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('11/1〜11/10')
            ->assertSee('☑ 0/1');
    }

    public function test_カードに絞り込み用の値が入る(): void
    {
        $this->task->update(['status' => 'doing', 'ended_on' => '2026-11-10']);
        $this->task->assignees()->attach($this->user);

        $this->actingAs($this->user)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('data-status="doing"', false)
            ->assertSee('data-due="2026-11-10"', false)
            ->assertSee('data-assignees="'.$this->user->id.'"', false);
    }

    public function test_リンクからタスクを開ける(): void
    {
        $this->actingAs($this->user)->get(route('projects.show', [$this->project, 'task' => $this->task->id]))
            ->assertOk()
            ->assertSee(route('tasks.show', $this->task), false);
    }
}

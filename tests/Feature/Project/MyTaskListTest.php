<?php

namespace Tests\Feature\Project;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * タスク一覧（参加しているプロジェクト全体で、自分が担当者になっているタスク）
 */
class MyTaskListTest extends TestCase
{
    use RefreshDatabase;

    private function projectOf(User $user, string $name = 'プロジェクト'): Project
    {
        $project = Project::factory()->ownedBy($user)->create(['name' => $name]);
        $project->createDefaultTaskLists();

        return $project;
    }

    private function task(Project $project, User $creator, string $title, array $assignees = [], TaskStatus $status = TaskStatus::Todo, ?string $endedOn = null): Task
    {
        $task = $project->taskLists()->first()->tasks()->make(['title' => $title, 'position' => 0]);
        $task->project_id = $project->id;
        $task->number = (int) $project->tasks()->max('number') + 1;
        $task->created_by = $creator->id;
        $task->updated_by = $creator->id;
        $task->status = $status;
        $task->ended_on = $endedOn;
        $task->save();
        $task->assignees()->attach(collect($assignees)->pluck('id'));

        return $task;
    }

    public function test_ナビにタスク一覧がある(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('my-tasks.index'));
    }

    public function test_参加しているプロジェクト全体で自分が担当のタスクだけを出す(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $a = $this->projectOf($me, 'プロジェクトA');
        $b = $this->projectOf($other, 'プロジェクトB');
        $b->users()->attach($me->id, ['role' => 'member']);

        $this->task($a, $me, 'Aの自分のタスク', [$me]);
        $this->task($b, $other, 'Bの自分のタスク', [$me, $other]);
        $this->task($b, $other, '他の人のタスク', [$other]);
        $this->task($a, $me, '担当なし');

        $this->actingAs($me)->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee('Aの自分のタスク')
            ->assertSee('Bの自分のタスク')
            ->assertSee('プロジェクトB')
            ->assertDontSee('他の人のタスク')
            ->assertDontSee('担当なし');
    }

    public function test_ボードに出ていないものとアーカイブ中のプロジェクトのものは出さない(): void
    {
        $me = User::factory()->create();
        $project = $this->projectOf($me);

        $this->task($project, $me, '出るタスク', [$me]);
        $this->task($project, $me, 'アーカイブしたタスク', [$me])->archive();

        $listArchived = $this->task($project, $me, 'アーカイブしたリストのタスク', [$me]);
        $listArchived->task_list_id = $project->taskLists()->skip(1)->first()->id;
        $listArchived->save();
        $project->taskLists()->skip(1)->first()->archive();

        $archivedProject = $this->projectOf($me, '終わったプロジェクト');
        $this->task($archivedProject, $me, 'アーカイブ中のプロジェクトのタスク', [$me]);
        $archivedProject->archive();

        $this->actingAs($me)->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee('出るタスク')
            ->assertDontSee('アーカイブしたタスク')
            ->assertDontSee('アーカイブしたリストのタスク')
            ->assertDontSee('アーカイブ中のプロジェクトのタスク');
    }

    public function test_参加から外れたプロジェクトのタスクは出さない(): void
    {
        $me = User::factory()->create();
        $owner = User::factory()->create();
        $project = $this->projectOf($owner);
        $this->task($project, $owner, '外れたプロジェクトのタスク', [$me]);

        $this->actingAs($me)->get(route('my-tasks.index'))
            ->assertOk()
            ->assertDontSee('外れたプロジェクトのタスク');
    }

    public function test_完了と中止は既定では出さずすべてで出す(): void
    {
        $me = User::factory()->create();
        $project = $this->projectOf($me);

        $this->task($project, $me, '進行中のタスク', [$me], TaskStatus::Doing);
        $this->task($project, $me, '保留のタスク', [$me], TaskStatus::OnHold);
        $this->task($project, $me, '完了したタスク', [$me], TaskStatus::Done);
        $this->task($project, $me, '中止したタスク', [$me], TaskStatus::Canceled);

        $this->actingAs($me)->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee('進行中のタスク')
            ->assertSee('保留のタスク')
            ->assertDontSee('完了したタスク')
            ->assertDontSee('中止したタスク');

        $this->actingAs($me)->get(route('my-tasks.index', ['show' => 'all']))
            ->assertOk()
            ->assertSee('完了したタスク')
            ->assertSee('中止したタスク');
    }

    public function test_終了日の近い順に並べ終了日のないものは後ろにする(): void
    {
        $me = User::factory()->create();
        $project = $this->projectOf($me);

        $this->task($project, $me, '期限なし', [$me]);
        $this->task($project, $me, '期限が遅い', [$me], endedOn: '2026-12-20');
        $this->task($project, $me, '期限が早い', [$me], endedOn: '2026-11-12');

        $this->actingAs($me)->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSeeInOrder(['期限が早い', '期限が遅い', '期限なし']);
    }

    public function test_行からプロジェクトのボードでタスクを開ける(): void
    {
        $me = User::factory()->create();
        $project = $this->projectOf($me);
        $task = $this->task($project, $me, '開くタスク', [$me]);

        $this->actingAs($me)->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee(route('projects.show', [$project, 'task' => $task->id]), false);
    }

    public function test_期間は曜日付きで出す(): void
    {
        $this->travelTo('2026-11-11');
        $me = User::factory()->create();
        $project = $this->projectOf($me);
        $task = $this->task($project, $me, '期間のあるタスク', [$me], endedOn: '2026-11-13');
        $task->started_on = '2026-11-09';
        $task->save();

        $this->actingAs($me)->get(route('my-tasks.index'))->assertSee('11/9(月) 〜 11/13(金)');
        $this->actingAs($me)->get(route('projects.show', $project))->assertSee('11/9(月) 〜 11/13(金)');
    }
}

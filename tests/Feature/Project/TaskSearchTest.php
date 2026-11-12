<?php

namespace Tests\Feature\Project;

use App\Enums\TaskStatus;
use App\Http\Controllers\Task\TaskSearchController;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

/**
 * タスクの検索（ナビの検索欄）
 */
class TaskSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
        $this->project = $this->projectOf($this->me, '基幹システム');
    }

    private function projectOf(User $user, string $name): Project
    {
        $project = Project::factory()->ownedBy($user)->create(['name' => $name]);
        $project->createDefaultTaskLists();

        return $project;
    }

    private function task(Project $project, string $title, TaskStatus $status = TaskStatus::Todo): Task
    {
        $creator = $project->users()->first();
        $task = $project->taskLists()->first()->tasks()->make(['title' => $title, 'position' => 0]);
        $task->project_id = $project->id;
        $task->number = (int) $project->tasks()->max('number') + 1;
        $task->created_by = $creator->id;
        $task->updated_by = $creator->id;
        $task->status = $status;
        $task->save();

        return $task;
    }

    private function search(string $q)
    {
        return $this->actingAs($this->me)->get(route('tasks.search', ['q' => $q]))->assertOk();
    }

    public function test_ナビに検索欄がある(): void
    {
        $this->actingAs($this->me)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('taskSearch', false)
            ->assertSee(Js::from(route('tasks.search'))->toHtml(), false);
    }

    public function test_タイトルの一部で探せて結果からボードのタスクを開ける(): void
    {
        $task = $this->task($this->project, 'ログイン画面の不具合');
        $this->task($this->project, '請求書の出力');

        $this->search('不具合')
            ->assertSee('ログイン画面の不具合')
            ->assertSee('基幹システム')
            ->assertSee(route('projects.show', [$this->project, 'task' => $task->id]), false)
            ->assertDontSee('請求書の出力');
    }

    public function test_スペースで区切った言葉はすべて含むものだけ出す(): void
    {
        $this->task($this->project, 'ログイン画面の不具合');
        $this->task($this->project, '一覧画面の表示');

        // 全角スペースでも区切る。プロジェクト名にも当たる
        $this->search('基幹　画面 不具合')
            ->assertSee('ログイン画面の不具合')
            ->assertDontSee('一覧画面の表示');
    }

    public function test_番号で探せる(): void
    {
        $first = $this->task($this->project, '一つ目');
        $this->task($this->project, '二つ目');

        $this->search('#'.$first->number)
            ->assertSee('一つ目')
            ->assertDontSee('二つ目');
    }

    public function test_参加していないプロジェクトとボードに出ていないタスクは出さない(): void
    {
        $other = User::factory()->create();
        $this->task($this->projectOf($other, '他人のプロジェクト'), '他人の検索対象');

        $this->task($this->project, 'アーカイブした検索対象')->archive();

        $archivedProject = $this->projectOf($this->me, '終わったプロジェクト');
        $this->task($archivedProject, '終わった検索対象');
        $archivedProject->archive();

        $this->task($this->project, '出る検索対象');

        $this->search('検索対象')
            ->assertSee('出る検索対象')
            ->assertDontSee('他人の検索対象')
            ->assertDontSee('アーカイブした検索対象')
            ->assertDontSee('終わった検索対象');
    }

    public function test_完了したタスクは後ろに回す(): void
    {
        $this->task($this->project, '完了した作業', TaskStatus::Done);
        $this->task($this->project, '進行中の作業', TaskStatus::Doing);

        $this->search('作業')->assertSeeInOrder(['進行中の作業', '完了した作業']);
    }

    public function test_ワイルドカードの文字はそのまま探す(): void
    {
        $this->task($this->project, '進捗100%');
        $this->task($this->project, '進捗1000');

        $this->search('100%')
            ->assertSee('進捗100%')
            ->assertDontSee('進捗1000');
    }

    public function test_空の入力では何も出さず見つからないときはそう伝える(): void
    {
        $this->task($this->project, 'なにかのタスク');

        $this->search('  ')->assertDontSee('なにかのタスク')->assertDontSee('見つかりませんでした');
        $this->search('存在しない言葉')->assertSee('見つかりませんでした');
    }

    public function test_件数には上限がある(): void
    {
        foreach (range(1, TaskSearchController::LIMIT + 2) as $i) {
            $this->task($this->project, "まとめて作るタスク{$i}");
        }

        $html = $this->search('まとめて作る')->getContent();

        $this->assertSame(TaskSearchController::LIMIT, substr_count($html, 'data-search-item'));
    }
}

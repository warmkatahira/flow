<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * プロジェクト管理（システム管理者が全員のプロジェクトを見るだけの入口）
 */
class ProjectBrowseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TaskCommentAttachment::DISK);

        $this->admin = User::factory()->systemAdmin()->create();
        $this->owner = User::factory()->create(['name' => '山田']);
        // システム管理者は参加していない
        $this->project = Project::factory()->ownedBy($this->owner)->create(['name' => '営業部だけの企画']);
        $this->project->createDefaultTaskLists();

        $this->actingAs($this->owner)->post(route('tasks.store', $this->project->taskLists()->first()), ['title' => '山田の作業']);
        $this->task = Task::firstWhere('title', '山田の作業');

        $this->actingAs($this->owner)->post(route('tasks.comments.store', $this->task), [
            'body' => '資料です',
            'attachments' => [UploadedFile::fake()->createWithContent('見積書.xlsx', 'xlsx')],
        ]);
    }

    private function attachment(): TaskCommentAttachment
    {
        return TaskCommentAttachment::firstOrFail();
    }

    public function test_ユーザーからプロジェクトを辿って中身を見られる(): void
    {
        $personal = Project::createPersonalFor($this->owner);

        $this->actingAs($this->admin)->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('山田')
            ->assertSee(route('admin.projects.user', $this->owner));

        // My Project が先頭
        $this->actingAs($this->admin)->get(route('admin.projects.user', $this->owner))
            ->assertOk()
            ->assertSeeInOrder(['My Project', '営業部だけの企画'])
            ->assertSee(route('admin.projects.show', [$this->owner, $personal]))
            ->assertSee(route('admin.projects.show', [$this->owner, $this->project]));

        $this->actingAs($this->admin)->get(route('admin.projects.show', [$this->owner, $this->project]))
            ->assertOk()
            ->assertSee('山田の作業')
            ->assertSee('見積書.xlsx')
            ->assertSee('閲覧専用');

        $this->actingAs($this->admin)->get(route('admin.projects.task', [$this->owner, $this->project, $this->task]))
            ->assertOk()
            ->assertSee('山田の作業')
            ->assertSee('資料です')
            ->assertSee(route('admin.projects.attachment', $this->attachment()));
    }

    public function test_アーカイブ済みのプロジェクトも見られる(): void
    {
        $this->project->archive();

        $this->actingAs($this->admin)->get(route('admin.projects.user', $this->owner))->assertSee('アーカイブ中');
        $this->actingAs($this->admin)->get(route('admin.projects.show', [$this->owner, $this->project]))->assertOk()->assertSee('山田の作業');
    }

    public function test_削除済みのユーザーのプロジェクトも見られる(): void
    {
        $this->owner->delete();

        $this->actingAs($this->admin)->get(route('admin.projects.index'))->assertSee('削除済み');
        $this->actingAs($this->admin)->get(route('admin.projects.show', [$this->owner, $this->project]))->assertOk();
    }

    public function test_添付ファイルをダウンロードできる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.projects.attachment', [$this->attachment(), 'download' => 1]))
            ->assertOk();

        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_ふつうの画面からは今まで通り見られず操作もできない(): void
    {
        $this->actingAs($this->admin)->get(route('projects.show', $this->project))->assertNotFound();
        $this->actingAs($this->admin)->get(route('tasks.show', $this->task))->assertNotFound();
        $this->actingAs($this->admin)->get(route('attachments.show', $this->attachment()))->assertNotFound();
        $this->actingAs($this->admin)->post(route('tasks.comments.store', $this->task), ['body' => '見たよ'])->assertNotFound();
    }

    public function test_辿ってきたユーザーが参加していないプロジェクトやタスクは見せない(): void
    {
        $other = User::factory()->create();
        $otherProject = Project::factory()->ownedBy($other)->create();
        $otherProject->createDefaultTaskLists();

        $this->actingAs($this->admin)->get(route('admin.projects.show', [$other, $this->project]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.projects.task', [$other, $otherProject, $this->task]))->assertNotFound();
    }

    public function test_システム管理者以外は使えない(): void
    {
        foreach ([User::factory()->admin()->create(), $this->owner] as $viewer) {
            $this->actingAs($viewer)->get(route('admin.projects.index'))->assertForbidden();
            $this->actingAs($viewer)->get(route('admin.projects.show', [$this->owner, $this->project]))->assertForbidden();
            $this->actingAs($viewer)->get(route('admin.projects.attachment', $this->attachment()))->assertForbidden();
        }
    }

    public function test_メニューはシステム管理者にだけ出る(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('システム管理')
            ->assertSee(route('admin.projects.index'));

        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
            ->assertDontSee('システム管理')
            ->assertDontSee(route('admin.projects.index'));
    }

    public function test_閲覧しても何も書き込まない(): void
    {
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (! preg_match('/^\s*select/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->actingAs($this->admin)->get(route('admin.projects.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.projects.user', $this->owner))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.projects.show', [$this->owner, $this->project]))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.projects.task', [$this->owner, $this->project, $this->task]))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.projects.attachment', $this->attachment()))->assertOk();

        $this->assertSame([], $writes);
        $this->assertSame(1, TaskComment::count());
    }
}

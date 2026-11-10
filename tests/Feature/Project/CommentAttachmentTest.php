<?php

namespace Tests\Feature\Project;

use App\Enums\ListColor;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    private TaskList $list;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TaskCommentAttachment::DISK);

        $this->owner = User::factory()->create(['name' => '持ち主']);
        $this->project = Project::factory()->ownedBy($this->owner)->create();
        $this->list = $this->project->taskLists()->create(['name' => 'ToDo', 'color' => ListColor::Sky->value, 'position' => 0]);

        $this->actingAs($this->owner)->post(route('tasks.store', $this->list), ['title' => 'タスク']);
        $this->task = Task::firstWhere('title', 'タスク');
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function comment(array $files, string $body = '<p>見てください</p>'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->post(route('tasks.comments.store', $this->task), ['body' => $body, 'attachments' => $files]);
    }

    public function test_コメントにファイルを添付できる(): void
    {
        $this->comment([
            UploadedFile::fake()->image('写真.png'),
            UploadedFile::fake()->create('見積書.pdf', 300, 'application/pdf'),
        ])->assertOk()
            ->assertSee('写真.png')
            ->assertSee('見積書.pdf');

        $attachments = TaskCommentAttachment::all();
        $this->assertCount(2, $attachments);
        $attachments->each(fn ($attachment) => Storage::disk(TaskCommentAttachment::DISK)->assertExists($attachment->path));
        $this->assertTrue($attachments->firstWhere('name', '写真.png')->isInlineImage());
        $this->assertFalse($attachments->firstWhere('name', '見積書.pdf')->isInlineImage());
    }

    public function test_ファイルだけでも投稿できる(): void
    {
        $this->comment([UploadedFile::fake()->create('メモ.txt', 1)], '')->assertOk();

        $this->assertSame(1, TaskComment::count());
        $this->assertSame(1, TaskCommentAttachment::count());
    }

    public function test_本文もファイルもなければ投稿できない(): void
    {
        $this->comment([], '')->assertSessionHasErrors('body');

        $this->assertSame(0, TaskComment::count());
    }

    public function test_数と大きさの上限を超えると投稿できない(): void
    {
        $this->comment(array_map(fn ($i) => UploadedFile::fake()->create("{$i}.txt", 1), range(1, 6)))->assertSessionHasErrors('attachments');
        $this->comment([UploadedFile::fake()->create('大きい.zip', TaskCommentAttachment::MAX_KILOBYTES + 1)])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, TaskComment::count());
    }

    public function test_参加者はダウンロードでき_画像以外は必ずダウンロードになる(): void
    {
        $this->comment([UploadedFile::fake()->image('写真.png'), UploadedFile::fake()->create('page.html', 1, 'text/html')]);

        $image = TaskCommentAttachment::firstWhere('name', '写真.png');
        $html = TaskCommentAttachment::firstWhere('name', 'page.html');

        $this->actingAs($this->owner)->get(route('attachments.show', $image))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $response = $this->actingAs($this->owner)->get(route('attachments.show', $html))->assertOk();
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_PDFはプレビューでき_ダウンロードも選べる(): void
    {
        $this->comment([UploadedFile::fake()->create('見積書.pdf', 10, 'application/pdf')])
            ->assertSee('見積書.pdf のプレビュー');

        $pdf = TaskCommentAttachment::first();
        $this->assertTrue($pdf->isPdf());

        $response = $this->actingAs($this->owner)->get(route('attachments.show', $pdf))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));

        $download = $this->actingAs($this->owner)->get(route('attachments.show', [$pdf, 'download' => 1]))->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
    }

    public function test_参加していない人はダウンロードできない(): void
    {
        $this->comment([UploadedFile::fake()->create('秘密.pdf', 1)]);

        $this->actingAs(User::factory()->create())
            ->get(route('attachments.show', TaskCommentAttachment::first()))
            ->assertNotFound();
    }

    public function test_コメントを消すとファイルも消える(): void
    {
        $this->comment([UploadedFile::fake()->create('メモ.txt', 1)]);
        $attachment = TaskCommentAttachment::first();

        $this->actingAs($this->owner)->delete(route('comments.destroy', TaskComment::first()))->assertOk();

        $this->assertSame(0, TaskCommentAttachment::count());
        Storage::disk(TaskCommentAttachment::DISK)->assertMissing($attachment->path);
    }

    public function test_タスクを完全に削除するとファイルも消える(): void
    {
        $this->comment([UploadedFile::fake()->create('メモ.txt', 1)]);
        $path = TaskCommentAttachment::first()->path;

        $this->actingAs($this->owner)->post(route('tasks.archive', $this->task));
        $this->actingAs($this->owner)->delete(route('tasks.destroy', $this->task));

        Storage::disk(TaskCommentAttachment::DISK)->assertMissing($path);
    }

    public function test_プロジェクトを完全に削除するとファイルも消える(): void
    {
        $this->comment([UploadedFile::fake()->create('メモ.txt', 1)]);
        $path = TaskCommentAttachment::first()->path;

        $this->actingAs($this->owner)->post(route('projects.archive', $this->project));
        $this->actingAs($this->owner)->delete(route('projects.destroy', $this->project));

        Storage::disk(TaskCommentAttachment::DISK)->assertMissing($path);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 定期タスク（決まった日にタスクを自動で作るひな形と繰り返しの設定）
 *
 * ひな形のタグ・担当者・フォロー・チェックリストは JSON で持つ。
 * 作るときに、その時点でプロジェクトに残っているタグと参加者だけに絞るので、外部キーは張らない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // 入れる先のリスト。消えたりアーカイブされたりしていたら、作るときに先頭のリストへ入れる
            $table->foreignId('task_list_id')->nullable()->constrained()->nullOnDelete();

            // ひな形。タイトルには {年月} などの差し込みを書ける
            $table->string('title');
            $table->mediumText('description')->nullable();
            $table->json('label_ids');
            $table->json('assignee_ids');
            $table->json('follower_ids');
            // [{ title, items: [{ title, assignee_id }] }]
            $table->json('checklists');

            // 繰り返し（App\Enums\RecurrenceFrequency）
            $table->string('frequency');
            // 毎週の曜日（ISO: 1=月 〜 7=日）
            $table->json('weekdays')->nullable();
            // 毎月・毎年の日（31 は月末。その月にない日は月末に寄せる）
            $table->unsignedTinyInteger('month_day')->nullable();
            // 毎年の月
            $table->unsignedTinyInteger('month')->nullable();
            // 毎月の第n◯曜日（5 は最終）
            $table->unsignedTinyInteger('nth')->nullable();
            $table->unsignedTinyInteger('nth_weekday')->nullable();
            // 土日・祝日に当たったとき（App\Enums\HolidayRule）
            $table->string('holiday_rule')->default('keep');

            // 終了日は作った日の何日後か（null なら終了日なし）
            $table->unsignedSmallInteger('due_days')->nullable();
            // この日から作り始め、この日を過ぎたら作らない
            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            // 最後に作った回の「本来の日」（土日・祝日でずらす前）。次の回はこれより後から探す
            $table->date('last_base_on')->nullable();
            // 次に作る日（ずらしたあと）。終わったら null
            $table->date('next_run_on')->nullable()->index();
            $table->timestamp('paused_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_tasks');
    }
};

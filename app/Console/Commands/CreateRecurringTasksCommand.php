<?php

namespace App\Console\Commands;

use App\Events\BoardChanged;
use App\Models\RecurringTask;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CreateRecurringTasksCommand extends Command
{
    protected $signature = 'flow:recurring-tasks';

    protected $description = '作る日が来た定期タスクから、タスクを作る（毎朝 6:00 に routes/console.php から動かす）';

    /**
     * 作り損ねた回があっても、定期タスクごとに最新の1回だけ作る（App\Models\RecurringTask::runDue）。
     * 1件の失敗で他の定期タスクまで止まらないよう、1件ずつ受け止めて続ける。
     */
    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $created = 0;

        RecurringTask::query()->due($today)->with(['project', 'list'])->each(function (RecurringTask $recurring) use ($today, &$created) {
            try {
                if ($task = $recurring->runDue($today)) {
                    $created++;
                    BoardChanged::notify($recurring->project);
                    $this->line("#{$task->number} {$task->title}（{$recurring->project->name}）");
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error("定期タスク {$recurring->getKey()} を作れませんでした: {$exception->getMessage()}");
            }
        });

        $this->info("{$created} 件のタスクを作りました。");

        return self::SUCCESS;
    }
}

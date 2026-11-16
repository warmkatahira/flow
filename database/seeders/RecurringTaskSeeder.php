<?php

namespace Database\Seeders;

use App\Enums\HolidayRule;
use App\Enums\RecurrenceFrequency;
use App\Models\Project;
use App\Models\RecurringTask;
use Illuminate\Database\Seeder;

/**
 * 動作確認用の定期タスク（本番では作らない。すでにあるプロジェクトには入れない）
 */
class RecurringTaskSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $project = Project::where('name', '○○商事 基幹システム')->first();

        if (! $project || $project->recurringTasks()->exists() || ! ($list = $project->taskLists()->active()->orderBy('position')->first())) {
            return;
        }

        $owner = $project->users()->orderBy('users.id')->first();
        $members = $project->users()->pluck('users.id')->all();

        $templates = [
            [
                'title' => '月次締め（{年月}）',
                'frequency' => RecurrenceFrequency::MonthlyDay,
                'month_day' => 25,
                'holiday_rule' => HolidayRule::Before,
                'due_days' => 5,
                'checklists' => [[
                    'title' => '締めの手順',
                    'items' => [
                        ['title' => '売上の計上漏れを確かめる', 'assignee_id' => null],
                        ['title' => '請求書を出す', 'assignee_id' => null],
                        ['title' => '経理へ報告する', 'assignee_id' => null],
                    ],
                ]],
            ],
            [
                'title' => '週次の定例の準備 {日付}',
                'frequency' => RecurrenceFrequency::Weekly,
                'weekdays' => [1],
                'holiday_rule' => HolidayRule::After,
                'due_days' => 0,
                'checklists' => [],
            ],
        ];

        foreach ($templates as $template) {
            $recurring = $project->recurringTasks()->make([
                ...$template,
                'task_list_id' => $list->id,
                'label_ids' => [],
                'assignee_ids' => $members,
                'follower_ids' => [],
                'starts_on' => today(),
            ]);
            $recurring->created_by = $owner?->id;
            $recurring->updated_by = $owner?->id;
            $recurring->refreshNextRun();
            $recurring->save();
        }
    }
}

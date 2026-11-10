<?php

namespace Database\Seeders;

use App\Enums\ListColor;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * projects / project_user テーブル
     */
    public function run(): void
    {
        // 動作確認用のプロジェクトは本番では作らない
        if (app()->isProduction()) {
            return;
        }

        $systemAdmin = User::where('email', config('flow.test_account.email'))->first();
        $admin = User::where('email', 'admin@example.com')->first();
        $general = User::where('email', 'user@example.com')->first();

        if (! $systemAdmin || ! $admin || ! $general) {
            return;
        }

        // 参加者の見え方を確かめられるように、あえて参加者の組み合わせを変えてある。
        // グループなしのプロジェクトも1つ混ぜてある
        $projects = [
            ['社内システム刷新', '社内', [
                $systemAdmin->id => ProjectRole::Owner,
                $admin->id => ProjectRole::Member,
                $general->id => ProjectRole::Member,
            ]],
            ['採用サイトリニューアル', '社内', [
                $admin->id => ProjectRole::Owner,
                $general->id => ProjectRole::Member,
            ]],
            ['○○商事 基幹システム', '受託', [
                $systemAdmin->id => ProjectRole::Owner,
                $admin->id => ProjectRole::Member,
            ]],
            // システム管理者が参加していないプロジェクト。
            // ダッシュボードにも詳細にも出てこないことの確認用
            ['営業部だけの企画', null, [
                $general->id => ProjectRole::Owner,
            ]],
        ];

        foreach ($projects as [$name, $groupName, $participants]) {
            $project = Project::updateOrCreate(['name' => $name]);
            $project->createDefaultLabels();

            $project->users()->sync(
                array_map(fn (ProjectRole $role) => ['role' => $role->value], $participants),
            );

            // グループは個人のものなので、参加者それぞれの分を作って割り当てる
            if ($groupName) {
                foreach (array_keys($participants) as $userId) {
                    $group = ProjectGroup::where('user_id', $userId)->where('name', $groupName)->first();

                    if (! $group) {
                        // user_id と position は fillable に載せていないので明示的に入れる
                        $group = new ProjectGroup(['name' => $groupName]);
                        $group->user_id = $userId;
                        $group->position = $groupName === '社内' ? 0 : 1;
                        $group->save();
                    }

                    $project->users()->updateExistingPivot($userId, ['group_id' => $group->id]);
                }
            }

            $this->seedBoard($project);
        }
    }

    /**
     * 動作確認用のカンバン（すでにリストがあるプロジェクトには入れない）
     */
    private function seedBoard(Project $project): void
    {
        if ($project->taskLists()->exists()) {
            return;
        }

        $lists = [
            ['ToDo リスト', ListColor::Sky, ['運賃振り分けに関するメモ', '8月の経理業務メモ', 'KINTAI の詳細', 'フロムアイズ改修']],
            ['進行中', ListColor::Emerald, ['人事・総務・経理で調整する事項', 'CMS 構築']],
            ['入れ替え待ち', ListColor::Amber, []],
            ['完了', ListColor::Rose, []],
        ];

        $number = 0;
        // 作成者と最終更新者は、最初のオーナーにしておく
        $ownerId = $project->users()->wherePivot('role', ProjectRole::Owner->value)->orderBy('users.id')->value('users.id');

        foreach ($lists as $position => [$name, $color, $titles]) {
            $list = $project->taskLists()->create([
                'name' => $name,
                'color' => $color->value,
                'position' => $position,
            ]);

            foreach ($titles as $taskPosition => $title) {
                $task = $list->tasks()->make([
                    'title' => $title,
                    'position' => $taskPosition,
                ]);

                $task->project_id = $project->getKey();
                $task->number = ++$number;
                $task->created_by = $ownerId;
                $task->updated_by = $ownerId;
                $task->save();
            }
        }
    }
}

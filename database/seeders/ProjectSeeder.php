<?php

namespace Database\Seeders;

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

        $groups = collect(['社内', '受託'])
            ->mapWithKeys(fn (string $name) => [$name => ProjectGroup::firstOrCreate(['name' => $name])]);

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
            $project = Project::updateOrCreate(
                ['name' => $name],
                ['group_id' => $groupName ? $groups[$groupName]->id : null],
            );

            $project->users()->sync(
                array_map(fn (ProjectRole $role) => ['role' => $role->value], $participants),
            );
        }
    }
}

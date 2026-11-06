<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 好きな色を選べるよう、リストの色を名前ではなくカラーコードで持つ
 */
return new class extends Migration
{
    private const COLORS = [
        'sky' => '#00a6f4',
        'emerald' => '#00bc7d',
        'amber' => '#ffba00',
        'rose' => '#ff2056',
        'violet' => '#7f22fe',
        'slate' => '#90a1b9',
        'indigo' => '#625fff',
        'pink' => '#fb64b6',
        'orange' => '#ff6900',
        'lime' => '#7ccf00',
        'teal' => '#00bba7',
        'brown' => '#973c00',
    ];

    public function up(): void
    {
        foreach (self::COLORS as $name => $hex) {
            DB::table('task_lists')->where('color', $name)->update(['color' => $hex]);
        }

        Schema::table('task_lists', function (Blueprint $table) {
            $table->string('color', 7)->default('#00a6f4')->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_lists', function (Blueprint $table) {
            $table->string('color')->default('sky')->change();
        });

        foreach (self::COLORS as $name => $hex) {
            DB::table('task_lists')->where('color', $hex)->update(['color' => $name]);
        }

        // 名前のない色は戻せないので既定の色にする
        DB::table('task_lists')->where('color', 'like', '#%')->update(['color' => 'sky']);
    }
};

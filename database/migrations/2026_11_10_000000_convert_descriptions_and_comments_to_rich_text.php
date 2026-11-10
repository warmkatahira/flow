<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 説明とコメントを書式付き（HTML）にしたので、今までの文章を行ごとの段落に直す
 *
 * タグの分だけ長くなるので、列も text（64KB）から mediumText に広げる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->mediumText('description')->nullable()->change());
        Schema::table('task_comments', fn (Blueprint $table) => $table->mediumText('body')->change());

        foreach (['tasks' => 'description', 'task_comments' => 'body'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(500, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([$column => RichText::fromPlainText($row->{$column})]);
                }
            });
        }
    }

    /**
     * 書式を外して文章に戻す（付けた書式は失われる）
     */
    public function down(): void
    {
        foreach (['tasks' => 'description', 'task_comments' => 'body'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(500, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([$column => RichText::toPlainText($row->{$column})]);
                }
            });
        }

        Schema::table('tasks', fn (Blueprint $table) => $table->text('description')->nullable()->change());
        Schema::table('task_comments', fn (Blueprint $table) => $table->text('body')->change());
    }
};

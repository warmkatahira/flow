<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * カンバンのリスト（列）
 *
 * 1プロジェクト＝1ボードとして、リストはプロジェクト直下に置く。
 * 複数ボードが必要になったら間に boards を挟む。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // #rrggbb（プリセットは App\Enums\ListColor）
            $table->string('color', 7)->default('#00a6f4');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->timestamp('archived_at')->nullable()->index();

            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};

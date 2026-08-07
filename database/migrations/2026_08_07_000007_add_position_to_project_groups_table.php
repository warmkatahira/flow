<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_groups', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('name');
        });

        // すでにあるグループには、それまでの並び（名前順）で番号を振る
        DB::table('project_groups')->orderBy('name')->get()->each(
            fn (object $group, int $position) => DB::table('project_groups')
                ->where('id', $group->id)
                ->update(['position' => $position]),
        );
    }

    public function down(): void
    {
        Schema::table('project_groups', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};

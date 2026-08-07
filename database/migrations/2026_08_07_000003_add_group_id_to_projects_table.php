<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // グループへの所属は任意。グループを消してもプロジェクトは残す
            $table->foreignId('group_id')
                ->nullable()
                ->after('name')
                ->constrained('project_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};

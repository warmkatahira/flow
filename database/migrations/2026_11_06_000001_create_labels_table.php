<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プロジェクトごとのラベルの種類
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};

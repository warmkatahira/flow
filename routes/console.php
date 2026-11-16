<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 定期タスク。朝 6:00 にその日の分をまとめて作る（本番では cron で schedule:run を毎分動かす）
Schedule::command('flow:recurring-tasks')->dailyAt('06:00')->withoutOverlapping();

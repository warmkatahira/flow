<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';
    case Canceled = 'canceled';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Todo => '未着手',
            self::Doing => '進行中',
            self::Done => '完了',
            self::Canceled => '中止',
            self::OnHold => '保留',
        };
    }

    /**
     * 文字とアイコンの色
     */
    public function textClass(): string
    {
        return match ($this) {
            self::Todo, self::Doing => 'text-sky-500',
            self::Done => 'text-emerald-500',
            self::Canceled => 'text-rose-500',
            self::OnHold => 'text-amber-500',
        };
    }
}

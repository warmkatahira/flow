<?php

namespace App\Enums;

/**
 * 定期タスクの日が土日・祝日に当たったときの扱い
 */
enum HolidayRule: string
{
    case Keep = 'keep';
    case Before = 'before';
    case After = 'after';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::Keep => 'そのまま作る',
            self::Before => '前の平日に作る',
            self::After => '後の平日に作る',
            self::Skip => '作らない',
        };
    }

    /**
     * 一覧に出す短い説明（そのまま作るときは何も出さない）
     */
    public function summary(): ?string
    {
        return match ($this) {
            self::Keep => null,
            self::Before => '土日祝は前の平日',
            self::After => '土日祝は後の平日',
            self::Skip => '土日祝は作らない',
        };
    }

    public function shifts(): bool
    {
        return $this === self::Before || $this === self::After;
    }
}

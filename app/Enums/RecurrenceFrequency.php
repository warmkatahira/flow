<?php

namespace App\Enums;

/**
 * 定期タスクの繰り返し方
 */
enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';
    case MonthlyDay = 'monthly_day';
    case MonthlyNth = 'monthly_nth';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => '毎日',
            self::Weekdays => '平日（月〜金）',
            self::Weekly => '毎週',
            self::MonthlyDay => '毎月（日付）',
            self::MonthlyNth => '毎月（第n◯曜日）',
            self::Yearly => '毎年',
        };
    }

    /**
     * 毎日・平日は、休日の前後へずらすと隣の回とぶつかるので「そのまま」か「作らない」だけにする
     */
    public function canShift(): bool
    {
        return ! in_array($this, [self::Daily, self::Weekdays], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}

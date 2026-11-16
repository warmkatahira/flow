<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Yasumi\Yasumi;

/**
 * 日本の土日・祝日の判定（祝日は Yasumi で計算する。振替休日・国民の休日も含む）
 */
class JapaneseCalendar
{
    /** @var array<int, array<string, true>> 年ごとの祝日（Y-m-d） */
    private static array $holidays = [];

    public static function isHoliday(CarbonInterface $date): bool
    {
        $year = $date->year;

        self::$holidays[$year] ??= collect(Yasumi::create('Japan', $year, 'ja_JP')->getHolidayDates())
            ->mapWithKeys(fn (string $day) => [$day => true])
            ->all();

        return isset(self::$holidays[$year][$date->format('Y-m-d')]);
    }

    /**
     * 土日でも祝日でもない日
     */
    public static function isBusinessDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! self::isHoliday($date);
    }
}

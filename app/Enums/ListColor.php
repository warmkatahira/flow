<?php

namespace App\Enums;

/**
 * リスト（列）の色
 *
 * Tailwind のクラスは動的に組み立てるとビルド時に拾われないので、
 * 使う組み合わせをここに書き切っておく。
 */
enum ListColor: string
{
    case Sky = 'sky';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Violet = 'violet';
    case Slate = 'slate';

    public function label(): string
    {
        return match ($this) {
            self::Sky => '青',
            self::Emerald => '緑',
            self::Amber => '黄',
            self::Rose => '赤',
            self::Violet => '紫',
            self::Slate => 'グレー',
        };
    }

    /**
     * リストの見出しバー
     */
    public function headerClass(): string
    {
        return match ($this) {
            self::Sky => 'bg-sky-200 text-sky-900',
            self::Emerald => 'bg-emerald-200 text-emerald-900',
            self::Amber => 'bg-amber-200 text-amber-900',
            self::Rose => 'bg-rose-200 text-rose-900',
            self::Violet => 'bg-violet-200 text-violet-900',
            self::Slate => 'bg-slate-200 text-slate-800',
        };
    }

    /**
     * 色を選ぶボタンの見本
     */
    public function swatchClass(): string
    {
        return match ($this) {
            self::Sky => 'bg-sky-400',
            self::Emerald => 'bg-emerald-400',
            self::Amber => 'bg-amber-400',
            self::Rose => 'bg-rose-400',
            self::Violet => 'bg-violet-400',
            self::Slate => 'bg-slate-400',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $color) => $options + [$color->value => $color->label()],
            [],
        );
    }
}

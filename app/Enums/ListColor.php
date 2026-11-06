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
    // 選ぶボタンはこの順に並ぶ。先頭が新しいリストの初期値
    case Sky = 'sky';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Pink = 'pink';
    case Rose = 'rose';
    case Orange = 'orange';
    case Amber = 'amber';
    case Lime = 'lime';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Brown = 'brown';
    case Slate = 'slate';

    public function label(): string
    {
        return match ($this) {
            self::Sky => '青',
            self::Indigo => '藍',
            self::Violet => '紫',
            self::Pink => 'ピンク',
            self::Rose => '赤',
            self::Orange => 'オレンジ',
            self::Amber => '黄',
            self::Lime => '黄緑',
            self::Emerald => '緑',
            self::Teal => '青緑',
            self::Brown => '茶',
            self::Slate => 'グレー',
        };
    }

    /**
     * リスト上端の帯と、色を選ぶボタンの見本
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Sky => 'bg-sky-500',
            self::Indigo => 'bg-indigo-500',
            self::Violet => 'bg-violet-600',
            self::Pink => 'bg-pink-400',
            self::Rose => 'bg-rose-500',
            self::Orange => 'bg-orange-500',
            self::Amber => 'bg-amber-400',
            self::Lime => 'bg-lime-500',
            self::Emerald => 'bg-emerald-500',
            self::Teal => 'bg-teal-500',
            self::Brown => 'bg-amber-800',
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

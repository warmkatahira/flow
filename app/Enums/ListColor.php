<?php

namespace App\Enums;

/**
 * リスト（列）の色のプリセット
 *
 * リストには好きな色も付けられるので、task_lists.color はカラーコードで持つ。
 * ここにあるのは選択肢だけで、この中の値とは限らない。
 */
enum ListColor: string
{
    /** task_lists.color に入れられる形式 */
    public const PATTERN = '/^#[0-9a-fA-F]{6}$/';

    // 選ぶボタンはこの順に並ぶ。先頭が新しいリストの初期値
    case Sky = '#00a6f4';
    case Indigo = '#625fff';
    case Violet = '#7f22fe';
    case Pink = '#fb64b6';
    case Rose = '#ff2056';
    case Orange = '#ff6900';
    case Amber = '#ffba00';
    case Lime = '#7ccf00';
    case Emerald = '#00bc7d';
    case Teal = '#00bba7';
    case Brown = '#973c00';
    case Slate = '#90a1b9';

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
}

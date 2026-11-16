<?php

namespace App\Enums;

/**
 * リスト（列）の色のプリセット
 *
 * リストには好きな色も付けられるので、task_lists.color はカラーコードで持つ。
 * ここにあるのは選択肢だけで、この中の値とは限らない。
 *
 * タグには値（濃い色）をそのまま、リストには pale()（淡い色）を使う。
 * ボードのカードはリストの色の付箋になるので、リストは淡い色を並べ、タグは付箋のどの色の上でも
 * 明るさの差が 3:1 以上になる濃さにしてある（同じ色相のリストとタグでも埋もれない）。
 * そのため「黄」「黄緑」は、名前より暗い、黄土色・オリーブ寄りの色になっている。
 */
enum ListColor: string
{
    /** task_lists.color に入れられる形式 */
    public const PATTERN = '/^#[0-9a-fA-F]{6}$/';

    // 選ぶボタンはこの順に並ぶ。先頭が新しいリストの初期値
    case Sky = '#0069a8';
    case Indigo = '#625fff';
    case Violet = '#7f22fe';
    case Pink = '#e60076';
    case Rose = '#ec003f';
    case Orange = '#ca3500';
    case Amber = '#bb4d00';
    case Lime = '#497d00';
    case Emerald = '#007a55';
    case Teal = '#00786f';
    case Brown = '#973c00';
    case Slate = '#62748e';

    /**
     * リストに使う淡い色。並びと名前は値と同じ
     */
    public function pale(): string
    {
        return match ($this) {
            self::Sky => '#bae6fd',
            self::Indigo => '#c7d2fe',
            self::Violet => '#ddd6fe',
            self::Pink => '#fbcfe8',
            self::Rose => '#fecdd3',
            self::Orange => '#fed7aa',
            self::Amber => '#fde68a',
            self::Lime => '#d9f99d',
            self::Emerald => '#a7f3d0',
            self::Teal => '#99f6e4',
            self::Brown => '#e7d3bf',
            self::Slate => '#e2e8f0',
        };
    }

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

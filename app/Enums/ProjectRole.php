<?php

namespace App\Enums;

/**
 * プロジェクト内での役割
 *
 * オーナーだけがプロジェクトの編集・アーカイブ・参加者の変更をできる。
 * ユーザー全体の権限（UserRole）とは別物で、システム管理者であっても
 * 参加していないプロジェクトには一切関与できない。
 */
enum ProjectRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    /**
     * 画面表示用の名称
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'オーナー',
            self::Member => '参加者',
        };
    }

    /**
     * 一覧のバッジ用クラス
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Owner => 'bg-amber-100 text-amber-700',
            self::Member => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * プルダウン用の [値 => 名称]
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $role) => $options + [$role->value => $role->label()],
            [],
        );
    }
}

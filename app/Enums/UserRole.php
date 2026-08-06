<?php

namespace App\Enums;

/**
 * ユーザーの権限
 *
 * システム管理者はすべての操作ができる。
 * 管理者と一般の区分けは未定のため、現時点では同じ扱いとする。
 */
enum UserRole: string
{
    case SystemAdmin = 'system_admin';
    case Admin = 'admin';
    case General = 'general';

    /**
     * 画面表示用の名称
     */
    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'システム管理者',
            self::Admin => '管理者',
            self::General => '一般',
        };
    }

    /**
     * 一覧のバッジ用クラス
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::SystemAdmin => 'bg-sky-100 text-sky-700',
            self::Admin => 'bg-emerald-100 text-emerald-700',
            self::General => 'bg-slate-100 text-slate-600',
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

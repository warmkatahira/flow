<?php

namespace App\Enums;

/**
 * フォローしたタスクの通知になる動き
 */
enum TaskActivity: string
{
    case Comment = 'comment';
    case Status = 'status';
    case Moved = 'moved';
    case Archived = 'archived';
    case Restored = 'restored';
    case Period = 'period';
    case Assignee = 'assignee';
    case Label = 'label';
    case Title = 'title';
    case Description = 'description';
    case Checklist = 'checklist';

    /**
     * 「○○ さんが」に続く文。detail は種類ごとの補足（ステータス名・リスト名・期間など）
     */
    public function message(?string $detail): string
    {
        return match ($this) {
            self::Comment => 'コメントしました',
            self::Status => "ステータスを「{$detail}」にしました",
            self::Moved => "「{$detail}」へ移動しました",
            self::Archived => 'アーカイブしました',
            self::Restored => 'アーカイブから戻しました',
            self::Period => $detail ? "期間を {$detail} にしました" : '期間を外しました',
            self::Assignee => '担当者を変更しました',
            self::Label => 'ラベルを変更しました',
            self::Title => 'タイトルを変更しました',
            self::Description => '説明を変更しました',
            self::Checklist => 'チェックリストを変更しました',
        };
    }

    /**
     * 同じ人の同じ種類の動きは、未読のうちは1件にまとめる（コメントは1件ずつ残す）
     */
    public function merges(): bool
    {
        return $this !== self::Comment;
    }
}

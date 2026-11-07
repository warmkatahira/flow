<?php

namespace App\Enums;

/**
 * コメントに付けられる絵文字
 *
 * 選ぶ欄とコメントの下に並ぶ順はこの順。task_comment_reactions.emoji には値（絵文字そのもの）を入れる
 */
enum Reaction: string
{
    case ThumbsUp = '👍';
    case Heart = '❤️';
    case Smile = '😄';
    case Tada = '🎉';
    case Pray = '🙏';
    case Eyes = '👀';
    case Check = '✅';
    case Thinking = '🤔';

    public function label(): string
    {
        return match ($this) {
            self::ThumbsUp => 'いいね',
            self::Heart => 'ハート',
            self::Smile => '笑顔',
            self::Tada => 'お祝い',
            self::Pray => 'ありがとう',
            self::Eyes => '見ています',
            self::Check => '確認済み',
            self::Thinking => '考え中',
        };
    }
}

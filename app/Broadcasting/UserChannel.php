<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * 本人だけが購読できるチャンネル（通知の合図を受け取る）
 */
class UserChannel
{
    public function join(User $user, string $id): bool
    {
        return (string) $user->getKey() === $id;
    }
}

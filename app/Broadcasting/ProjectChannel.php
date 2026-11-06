<?php

namespace App\Broadcasting;

use App\Models\Project;
use App\Models\User;

/**
 * プロジェクトのチャンネルを購読できるか
 *
 * 秘匿性は画面と同じで、参加していなければシステム管理者でも購読できない。
 */
class ProjectChannel
{
    public function join(User $user, Project $project): bool
    {
        return $user->is_active && ! $user->must_change_password && $project->hasParticipant($user);
    }
}

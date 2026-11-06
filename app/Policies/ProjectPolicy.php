<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * プロジェクトの認可
 *
 * 秘匿性を優先するため、参加していない人には「存在すら知らせない」。
 * そのため 403 ではなく 404 を返す（403 だと ID を総当たりして
 * プロジェクトの有無を調べられてしまうため）。
 *
 * システム管理者も例外ではない。AppServiceProvider の Gate::before は
 * プロジェクトに対しては素通りするようにしてある。
 */
class ProjectPolicy
{
    /**
     * 詳細を見られるか
     */
    public function view(User $user, Project $project): Response
    {
        return $project->hasParticipant($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * 作成できるか（ログインしていれば誰でも。作った人がオーナーになる）
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 編集できるか
     */
    public function update(User $user, Project $project): Response
    {
        if (! $project->hasParticipant($user)) {
            return Response::denyAsNotFound();
        }

        if (! $project->isOwner($user)) {
            return Response::deny('プロジェクトを編集できるのはオーナーだけです。');
        }

        return $project->isArchived()
            ? Response::deny('アーカイブ中のプロジェクトは編集できません。')
            : Response::allow();
    }

    /**
     * ボードを操作できるか（参加者なら誰でも。アーカイブ中は読み取り専用）
     */
    public function work(User $user, Project $project): Response
    {
        if (! $project->hasParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return $project->isArchived()
            ? Response::deny('アーカイブ中のプロジェクトは操作できません。')
            : Response::allow();
    }

    /**
     * アーカイブしたり元に戻したりできるか
     */
    public function archive(User $user, Project $project): Response
    {
        if (! $project->hasParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return $project->isOwner($user)
            ? Response::allow()
            : Response::deny('プロジェクトをアーカイブできるのはオーナーだけです。');
    }

    /**
     * 完全に削除できるか（いきなり消さないよう、アーカイブ済みのものだけ）
     */
    public function delete(User $user, Project $project): Response
    {
        if (! $project->hasParticipant($user)) {
            return Response::denyAsNotFound();
        }

        if (! $project->isOwner($user)) {
            return Response::deny('プロジェクトを削除できるのはオーナーだけです。');
        }

        return $project->isArchived()
            ? Response::allow()
            : Response::deny('アーカイブしてから削除してください。');
    }
}

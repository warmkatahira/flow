<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'icon_path' => null,
        ];
    }

    /**
     * 作成後にオーナーを1人ぶら下げる
     */
    public function ownedBy(User $user): static
    {
        return $this->afterCreating(
            fn (Project $project) => $project->users()->attach($user, ['role' => ProjectRole::Owner->value]),
        );
    }

    /**
     * 作成後に参加者をぶら下げる
     */
    public function withMembers(User ...$users): static
    {
        return $this->afterCreating(function (Project $project) use ($users) {
            foreach ($users as $user) {
                $project->users()->attach($user, ['role' => ProjectRole::Member->value]);
            }
        });
    }
}

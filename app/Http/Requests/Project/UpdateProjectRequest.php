<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('project'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_icon' => ['nullable', 'boolean'],
            'members' => ['array'],
            'members.*' => [Rule::exists('users', 'id')],
            'roles' => ['array'],
            'roles.*' => [Rule::enum(ProjectRole::class)],
        ];
    }

    /**
     * オーナーが1人もいなくなると誰も編集できないプロジェクトができてしまう
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $roles = $this->input('roles', []);

                $hasOwner = collect($this->input('members', []))
                    ->contains(fn ($id) => ($roles[$id] ?? null) === ProjectRole::Owner->value);

                if (! $hasOwner) {
                    $validator->errors()->add('members', 'オーナーを1人以上選んでください。');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'プロジェクト名',
            'icon' => 'アイコン画像',
            'members' => '参加者',
        ];
    }
}

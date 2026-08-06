<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * ルートの can:manage-users で制御している
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            // 変更しない場合は空のままにする
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating (POST) or updating (PUT) a staff account.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Super admin accounts can only be changed by another super admin.
        $target = $this->route('user');

        return ! $target?->isSuperAdmin() || $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $updating = $this->route('user') !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'role' => [
                'required',
                Rule::exists('roles', 'name'),
                // Only a super admin may hand out the super admin role.
                Rule::when(! $this->user()->isSuperAdmin(), Rule::notIn([Role::SUPERADMIN])),
            ],
            'is_active' => ['boolean'],
            'password' => [$updating ? 'nullable' : 'required', Password::min(8)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
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
            'label' => ['required', 'string', 'max:50', Rule::unique('roles')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            // Reserved permissions (like managing roles) are not in this list, so they can't be granted.
            'permissions.*' => ['string', 'distinct', Rule::in(Role::grantable())],
            'menus' => ['required', 'array', 'min:1'],
            'menus.*' => ['string', 'distinct', Rule::in(array_keys(Role::MENUS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $noMenus = 'Show at least one menu, or the role will have nowhere to go.';

        return ['menus.required' => $noMenus, 'menus.min' => $noMenus];
    }

    /**
     * @return array<string, mixed>
     */
    public function role(): array
    {
        $data = $this->validated();
        $data['permissions'] = array_values($data['permissions']);
        $data['menus'] = array_values($data['menus']);

        return $data;
    }
}

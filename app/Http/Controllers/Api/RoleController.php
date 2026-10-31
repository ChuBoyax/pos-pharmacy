<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'permissions' => Role::PERMISSIONS,
            'menus' => Role::MENUS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['name'] = Str::slug($data['label'], '_');

        if ($data['name'] === '' || Role::where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['label' => 'A role with this name already exists.']);
        }

        return response()->json(Role::create($data)->loadCount('users'), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->protect($role);

        $role->update($this->validated($request, $role));

        return response()->json($role->loadCount('users'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->protect($role);

        if ($count = $role->users()->count()) {
            throw ValidationException::withMessages([
                'role' => "{$count} ".Str::plural('user', $count).' still '.($count === 1 ? 'has' : 'have').' this role. Move them to another role first.',
            ]);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50', Rule::unique('roles')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            // Reserved permissions (like managing roles) are not in this list, so they can't be granted.
            'permissions.*' => ['string', 'distinct', Rule::in(Role::grantable())],
            'menus' => ['required', 'array', 'min:1'],
            'menus.*' => ['string', 'distinct', Rule::in(array_keys(Role::MENUS))],
        ], [
            'menus.required' => 'Show at least one menu, or the role will have nowhere to go.',
            'menus.min' => 'Show at least one menu, or the role will have nowhere to go.',
        ]);

        $data['permissions'] = array_values($data['permissions']);
        $data['menus'] = array_values($data['menus']);

        return $data;
    }

    private function protect(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            throw ValidationException::withMessages(['role' => 'The Super Admin role always has full access and cannot be changed.']);
        }
    }
}

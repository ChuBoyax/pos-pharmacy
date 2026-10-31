<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
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

    public function store(RoleRequest $request): JsonResponse
    {
        $data = $request->role();
        $data['name'] = Str::slug($data['label'], '_');

        if ($data['name'] === '' || Role::where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['label' => 'A role with this name already exists.']);
        }

        return response()->json(Role::create($data)->loadCount('users'), 201);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $this->protect($role);

        $role->update($request->role());

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

    private function protect(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            throw ValidationException::withMessages(['role' => 'The Super Admin role always has full access and cannot be changed.']);
        }
    }
}

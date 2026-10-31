<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->withCount('sales')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name');

        return response()->json($users->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['boolean'],
            'password' => ['required', Password::min(8)],
        ]);

        return response()->json(User::create($data), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['boolean'],
            'password' => ['nullable', Password::min(8)],
        ]);

        if ($user->is($request->user()) && ($data['role'] !== User::ROLE_ADMIN || ! ($data['is_active'] ?? true))) {
            throw ValidationException::withMessages(['role' => 'You cannot remove your own admin access.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        if (! $user->is_active || isset($data['password'])) {
            $user->tokens()->delete();
        }

        return response()->json($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        if ($user->sales()->exists()) {
            $user->update(['is_active' => false]);
            $user->tokens()->delete();

            return response()->json(['message' => 'User has sales records, so the account was deactivated instead.']);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}

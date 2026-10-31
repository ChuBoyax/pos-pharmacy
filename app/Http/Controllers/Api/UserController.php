<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('assignedRole:name,label')
            ->withCount('sales')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name');

        return response()->json($users->paginate(15));
    }

    public function store(UserRequest $request): JsonResponse
    {
        return response()->json(User::create($request->validated())->load('assignedRole:name,label'), 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // Nobody can lock themselves out, which also guarantees at least one super admin remains.
        if ($user->is($request->user()) && ($data['role'] !== $user->role || ! ($data['is_active'] ?? true))) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role or deactivate your own account.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        if (! $user->is_active || isset($data['password'])) {
            $user->tokens()->delete();
        }

        return response()->json($user->load('assignedRole:name,label'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403, 'Only a super admin can change a super admin account.');

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

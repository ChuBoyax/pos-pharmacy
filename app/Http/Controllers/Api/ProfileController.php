<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]));

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $user->authPayload(),
        ]);
    }

    public function password(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $request->password]);

        // Sign out every other device that was using the old password.
        $request->user()->tokens()->whereKeyNot($request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed.']);
    }
}

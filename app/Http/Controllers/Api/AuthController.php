<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        return response()->json([
            'message' => 'Login successful.',
            'token' => $user->createToken('pos-pharmacy')->plainTextToken,
            'user' => $user->authPayload(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->authPayload()]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Staff may share an account, so logging out ends its sessions on every device.
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}

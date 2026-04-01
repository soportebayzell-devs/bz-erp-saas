<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Returns a Sanctum token scoped to the current tenant.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
                    ->where('is_active', true)
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials you provided are incorrect.'],
            ]);
        }

        // Revoke previous tokens for this device (optional: remove for multi-device)
        $user->tokens()->where('name', 'api')->delete();

        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'user'       => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'role'   => $user->role,
                'tenant' => [
                    'id'       => $user->tenant->id,
                    'name'     => $user->tenant->name,
                    'slug'     => $user->tenant->slug,
                    'timezone' => $user->tenant->timezone,
                    'currency' => $user->tenant->currency,
                    'logo_url' => $user->tenant->logo_url,
                    'settings' => $user->tenant->settings,
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'phone'     => $user->phone,
            'avatar_url'=> $user->avatar_url,
            'tenant'    => $user->tenant,
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     * Rotate the token — delete current, issue a new one.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }
}

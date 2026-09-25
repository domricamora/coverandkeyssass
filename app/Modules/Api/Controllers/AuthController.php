<?php

namespace App\Modules\Api\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Token lifecycle: issue (email + password), who-am-I, revoke. */
class AuthController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'read_only' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        // One message for unknown email and wrong password: no account probing.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            \Illuminate\Support\Facades\Log::channel('security')->warning('api.token_failed', ['email' => mb_strtolower($data['email']), 'known_user' => $user !== null, 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'This account is suspended.']);
        }

        $abilities = ($data['read_only'] ?? false) ? ['read'] : ['read', 'write'];
        $token = $user->createToken($data['device_name'], $abilities);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'businesses' => $user->tenants()->where('tenants.status', 'active')->get()
                ->map(fn ($tenant) => ['slug' => $tenant->slug, 'name' => $tenant->name, 'role' => $user->tenantRole($tenant)?->name])
                ->values(),
            'token_abilities' => $user->currentAccessToken()?->abilities,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}

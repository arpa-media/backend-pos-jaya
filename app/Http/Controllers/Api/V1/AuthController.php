<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\MeResource;
use App\Http\Resources\Api\V1\Common\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $loginAs = strtoupper((string) ($validated['login_as'] ?? 'BACKOFFICE'));

        $user = \App\Models\User::query()
            ->where('nisj', $validated['nisj'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            // Gunakan format validasi agar klien mudah tampilkan error field
            throw ValidationException::withMessages([
                'nisj' => ['Invalid credentials.'],
            ]);
        }

        // Enforce flow rules
        $user->loadMissing('outlet');

        if ($loginAs === 'POS') {
            // POS: only SQUAD/CASHIER can login; outlet_code must match user outlet code
            $outletCode = strtoupper(trim((string) ($validated['outlet_code'] ?? '')));
            if ($outletCode === '') {
                throw ValidationException::withMessages([
                    'outlet_code' => ['Outlet code is required for POS login.'],
                ]);
            }

            if (!($user->hasRole('cashier') || $user->hasRole('squad'))) {
                return ApiResponse::error('Unauthorized for POS login', 'FORBIDDEN', 403);
            }

            $uOutletCode = strtoupper((string) optional($user->outlet)->code);
            if (!$user->outlet_id || $uOutletCode === '' || $uOutletCode !== $outletCode) {
                throw ValidationException::withMessages([
                    'outlet_code' => ['Outlet code does not match this user.'],
                ]);
            }
        } else {
            // BACKOFFICE: SQUAD requires either outlet_id or at least 1 permission
            if ($user->hasRole('squad')) {
                $hasOutlet = !empty($user->outlet_id);
                $hasPerm = $user->getAllPermissions()->count() > 0;
                if (!$hasOutlet && !$hasPerm) {
                    return ApiResponse::error('Squad has no outlet and no permission', 'FORBIDDEN', 403);
                }
            }
        }

        // Abilities untuk Sanctum token: gunakan semua permission user.
        $abilities = $user->getAllPermissions()->pluck('name')->values()->all();

        // Admin fallback (should not happen jika seeder benar)
        if ($user->hasRole('admin') && empty($abilities)) {
            $abilities = ['*'];
        }

        // Token label separation
        $tokenName = $loginAs === 'POS' ? 'pos' : 'backoffice';

        // Hapus token lama untuk label yang sama (mencegah token menumpuk di POS)
        $user->tokens()->where('name', $tokenName)->delete();

        $token = $user->createToken($tokenName, $abilities);

        return ApiResponse::ok([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'user' => new MeResource($user->fresh()->loadMissing('outlet')),
        ], 'Login success');
    }

    public function me(Request $request)
    {
        // Authorization via spatie permission middleware: permission:auth.me
        return ApiResponse::ok(new MeResource($request->user()->loadMissing('outlet')), 'OK');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::ok(null, 'Logged out');
    }
}

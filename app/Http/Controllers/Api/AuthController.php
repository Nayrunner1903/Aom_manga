<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function register(Request $request, CreateNewUser $createNewUser): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'password_confirmation' => ['required', 'string'],
        ]);
        $user = $createNewUser->create($validated);

        return $this->tokenResponse($this->guard()->login($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);
        $token = $this->guard()->attempt($credentials);
        abort_unless(is_string($token), 401, 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');

        return $this->tokenResponse($token);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }

    public function refresh(): JsonResponse
    {
        return $this->tokenResponse($this->guard()->refresh());
    }

    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return response()->json(['message' => 'ออกจากระบบ API แล้ว']);
    }

    protected function tokenResponse(string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ], $status)->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }
}

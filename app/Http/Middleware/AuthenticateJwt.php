<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && $token !== '', 401, 'กรุณาเข้าสู่ระบบด้วย Bearer token');

        $guard = Auth::guard('api');
        if (! $guard instanceof JWTGuard) {
            abort(500);
        }

        $guard->forgetUser();
        try {
            $guard->setRequest($request)->setToken($token);
            $user = $guard->user();
            $payload = $guard->payload();
        } catch (JWTException) {
            abort(401, 'Token ไม่ถูกต้อง หมดอายุ หรือถูกยกเลิกแล้ว');
        }

        abort_unless(
            $user instanceof User
            && is_int($payload->get('exp'))
            && $payload->get('exp') > now()->timestamp
            && is_string($payload->get('ver'))
            && hash_equals($user->jwtFingerprint(), $payload->get('ver'))
            && is_int($payload->get('renew_until'))
            && $payload->get('renew_until') > now()->timestamp,
            401,
            'กรุณาเข้าสู่ระบบใหม่'
        );

        $previousGuard = Auth::getDefaultDriver();
        Auth::shouldUse('api');

        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        } finally {
            $guard->forgetUser();
            Auth::shouldUse($previousGuard);
        }
    }
}

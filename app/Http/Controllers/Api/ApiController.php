<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class ApiController extends Controller
{
    protected function user(Request $request): User
    {
        $user = $request->user('api');
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function guard(): JWTGuard
    {
        $guard = Auth::guard('api');
        abort_unless($guard instanceof JWTGuard, 500);

        return $guard;
    }
}

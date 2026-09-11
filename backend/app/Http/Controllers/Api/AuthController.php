<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация SPA через сессию (Laravel Sanctum).
 *
 * Токены здесь не используются: фронт с домена из SANCTUM_STATEFUL_DOMAINS
 * работает по обычной сессионной куке, поэтому перед первым POST-запросом
 * ему нужно получить CSRF-куку через GET /sanctum/csrf-cookie.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->credentials(), $request->remember())) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        // Защита от фиксации сессии: после входа идентификатор меняется.
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Вы вышли из системы.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokenAuthenticate
{
    /**
     * Handle an incoming request.
     * Accepts both Bearer token and auth_token cookie.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->cookie('auth_token');

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tokenModel = PersonalAccessToken::findToken($token);

        if (! $tokenModel || ! $tokenModel->tokenable) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        // If sanctum.expiration is configured, enforce token lifetime
        $expirationMinutes = config('sanctum.expiration');
        if ($expirationMinutes) {
            $expiresAt = $tokenModel->created_at->addMinutes($expirationMinutes);
            if ($expiresAt->isPast()) {
                // optional: delete expired token
                $tokenModel->delete();
                return response()->json(['message' => 'Token expired.'], 401);
            }
        }

        // Set the authenticated user for the request
        Auth::setUser($tokenModel->tokenable);
        $request->setUserResolver(function () use ($tokenModel) {
            return $tokenModel->tokenable;
        });

        return $next($request);
    }
}

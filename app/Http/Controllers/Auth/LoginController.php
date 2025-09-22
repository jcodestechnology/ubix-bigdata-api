<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /**
     * Login and issue a personal access token (Sanctum).
     * Rate-limited and sets a secure HTTP-only cookie.
     */
//     public function login(Request $request)
//     {
//         $request->merge([
//             'email' => is_string($request->email) ? trim($request->email) : $request->email,
//         ]);

//         $validator = Validator::make($request->all(), [
//             'email' => 'required|string|email',
//             'password' => 'required|string|min:8',
            
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         // Throttle key (email|ip)
//         $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();

//         $maxAttempts = 5;
//         $decaySeconds = 60; // lockout window

//         if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
//             $available = RateLimiter::availableIn($throttleKey);
//             return response()->json([
//                 'message' => 'Too many login attempts. Try again later.'
//             ], 429)->header('Retry-After', $available);
//         }

//         $credentials = $request->only('email', 'password');

//         // Attempt authentication (uses configured guard & hashing)
//         if (! Auth::attempt($credentials)) {
//             RateLimiter::hit($throttleKey, $decaySeconds);
          
//             return response()->json(['message' => 'Invalid credentials'], 401);
//         }

//         // On success clear throttle
//         RateLimiter::clear($throttleKey);

//         $user = Auth::user();

//         // Token name includes device info and IP fingerprint
//         $device = $request->header('User-Agent', 'unknown');
//         $tokenName = 'auth_token_' . sha1($request->ip() . '|' . $device . '|' . now()->timestamp);

//         // Give token limited abilities — prefer least privilege (adjust as needed)
//         $abilities = ['auth'];

//         $plainTextToken = $user->createToken($tokenName, $abilities)->plainTextToken;

//         // Determine token expiry in minutes (use sanctum.expiration or default 7 days)
//         $expiryMinutes = config('sanctum.expiration') ?: (60 * 24 * 7);

//         // Secure cookie settings
//         $secure = app()->environment('production');
//         $cookie = cookie(
//     'auth_token',
//     $plainTextToken,
//     $expiryMinutes,
//     '/',                         
//     config('session.domain'),    
//     $secure,                     
//     true,                        
//     false,                       
//     'Strict'                     
// );


//         // Logging (without sensitive data) — useful for audit trails
//         Log::info('user.logged_in', [
//             'user_id' => $user->id,
//             'ip' => $request->ip(),
//             'ua' => substr($device, 0, 200),
//         ]);

 
//         return response()->json([
//             'message' => 'Login successful',
//             'user' => $user->only('id', 'name', 'email'),
//             'expires_in' => $expiryMinutes * 60, // seconds
//         ])->withCookie($cookie);
//     }

public function login(Request $request)
{
    $request->merge([
        'email' => is_string($request->email) ? trim($request->email) : $request->email,
    ]);

    $validator = Validator::make($request->all(), [
        'email' => 'required|string|email',
        'password' => 'required|string|min:8',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $credentials = $request->only('email', 'password');

    if (! Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $user = Auth::user();

    $tokenName = 'auth_token_' . sha1($request->ip() . '|' . $request->header('User-Agent', 'unknown') . '|' . now()->timestamp);
    $abilities = ['auth'];

    $plainTextToken = $user->createToken($tokenName, $abilities)->plainTextToken;

    $expiryMinutes = config('sanctum.expiration') ?: (60 * 24 * 7);

    return response()->json([
        'message' => 'Login successful',
        'user' => $user->only('id', 'name', 'email'),
        'token' => $plainTextToken,
        'expires_in' => $expiryMinutes * 60, // in seconds
    ]);
}


    /**
     * Logout by revoking the current token (from Authorization header or cookie).
     */
    public function logout(Request $request)
    {
        $token = $request->bearerToken() ?? $request->cookie('auth_token');

        if ($token) {
            $pat = PersonalAccessToken::findToken($token);
            if ($pat) {
                $pat->delete();
            }
        }

        // expire cookie
        $expiredCookie = cookie()->forget('auth_token');

        // invalidate session if using web guard
        if (Auth::check()) {
            Auth::logout();
        }

        return response()->json(['message' => 'Logout successful'])->withCookie($expiredCookie);
    }

    /**
     * Return currently authenticated user.
     * Requires the authentication middleware (below) to set the user.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
       Log::info($user);
        return response()->json(['user' => $user->only('id', 'name', 'email')]);
    }
}

<?php
namespace App\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AuthMiddleware {
    public function handle(Request $request, Closure $next) {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Legacy: demo-token logs in first user (for backward compatibility)
        if ($token === 'demo-token') {
            $user = User::first();
            if ($user) {
                auth()->login($user);
            }
            return $user ? $next($request) : response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Resolve user from encrypted token (set by login)
        try {
            $payload = Crypt::decryptString($token);
            $data = json_decode($payload, true);
            if (empty($data['user_id'])) {
                return response()->json(['error' => 'Invalid token'], 401);
            }
            $user = User::find($data['user_id']);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 401);
            }
            auth()->login($user);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        return $next($request);
    }
}

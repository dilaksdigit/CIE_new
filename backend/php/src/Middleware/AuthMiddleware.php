<?php
namespace App\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class AuthMiddleware {
    public function handle(Request $request, Closure $next) {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // For demo/standalone, we treat any non-empty token as valid for the first found user
        // Or we could implement a real token lookup if we had a tokens table.
        // For now, let's just use the first user for the demo if the token is "demo-token"
        if ($token === 'demo-token') {
            $user = User::first();
            auth()->login($user);
        } else {
            // Real implementation would look up token in DB
            // return response()->json(['error' => 'Invalid token'], 401);
            $user = User::first(); // Fallback for testing
            auth()->login($user);
        }

        return $next($request);
    }
}

<?php
namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;

class RBACMiddleware
{
    private const ROLE_ADMIN = 'ADMIN';

    /**
     * ADMIN: Full access to all fields and actions, modify 9-intent taxonomy,
     * manage users and roles. No restrictions — full system access.
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $user = auth()->user();
        $user->loadMissing('role');

        if (!$user->role) {
            return response()->json(['error' => 'Forbidden - No role assigned'], 403);
        }

        $userRole = strtoupper((string) $user->role->name);
        $allowedRoles = array_map('strtoupper', $allowedRoles);

        // ADMIN has full system access — no restrictions; bypass role list.
        if ($userRole === self::ROLE_ADMIN) {
            return $next($request);
        }

        if (!in_array($userRole, $allowedRoles)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "This action requires one of these roles: " . implode(', ', $allowedRoles)
            ], 403);
        }

        return $next($request);
    }
}

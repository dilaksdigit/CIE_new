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
        $user->loadMissing('roles');

        $roleNames = $user->roles->pluck('name')->map(fn ($n) => strtoupper((string) $n))->all();
        if (empty($roleNames)) {
            return response()->json(['error' => 'Forbidden - No role assigned'], 403);
        }

        $allowedRoles = array_map('strtoupper', $allowedRoles);

        // ADMIN has full system access — no restrictions; bypass role list.
        if (in_array(self::ROLE_ADMIN, $roleNames)) {
            return $next($request);
        }

        $hasAllowed = !empty(array_intersect($roleNames, $allowedRoles));
        if (!$hasAllowed) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "This action requires one of these roles: " . implode(', ', $allowedRoles)
            ], 403);
        }

        return $next($request);
    }
}

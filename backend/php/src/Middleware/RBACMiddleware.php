<?php
// SOURCE: CLAUDE.md Rule R7 (RBAC locked); CLAUDE.md Section 10 (RBAC on every endpoint); CIE_v231_Developer_Build_Pack RBAC section; RBAC-03, RBAC-04

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class RBACMiddleware
{
    private const ROLE_ADMIN = 'ADMIN';
    private const VALID_ROLES = [
        'CONTENT_EDITOR',
        'PRODUCT_SPECIALIST',
        'SEO_GOVERNOR',
        'CHANNEL_MANAGER',
        'FINANCE',
        'CONTENT_LEAD',
        'AI_OPS',
        'ADMIN',
    ];

    /** RBAC-03: Exact response body for admin-only route denial (CLAUDE.md Section 10). */
    private const ADMIN_DENIED_JSON = ['error' => 'Access denied. Admin role required.'];

    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $user = auth()->user();
        $user->loadMissing('roles');

        $roleNames = $user->roles->pluck('name')->map(fn ($n) => strtoupper((string) $n))->all();
        if (empty($roleNames)) {
            $this->logRbacDenial($request, [], $allowedRoles, in_array(self::ROLE_ADMIN, array_map('strtoupper', $allowedRoles)));
            return response()->json(['error' => 'Forbidden - No role assigned'], 403);
        }

        $allowedRoles = array_map('strtoupper', $allowedRoles);

        if (in_array(self::ROLE_ADMIN, $roleNames)) {
            return $next($request);
        }

        $hasAllowed = !empty(array_intersect($roleNames, $allowedRoles));
        if (!$hasAllowed) {
            $isAdminOnly = in_array(self::ROLE_ADMIN, $allowedRoles) && count($allowedRoles) === 1;
            $this->logRbacDenial($request, $roleNames, $allowedRoles, $isAdminOnly);
            if ($isAdminOnly) {
                return response()->json(self::ADMIN_DENIED_JSON, 403);
            }
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'This action requires one of these roles: ' . implode(', ', $allowedRoles),
            ], 403);
        }

        return $next($request);
    }

    /**
     * RBAC-04: Every 403 writes audit_log with entity_type rbac_denial; if same user_id has >=5 in 24h, write rbac_alert.
     * SOURCE: CIE_v231_Developer_Build_Pack RBAC audit spec; CLAUDE.md Section 9 (audit_log immutable).
     */
    private function logRbacDenial(Request $request, ?array $actualRoles, array $requiredRoles, bool $isAdminOnly = false): void
    {
        try {
            $userId = auth()->id();
            $actorIdStr = $userId ? (string) $userId : 'anonymous';
            $attemptedRoute = $request->path() . ' [' . $request->method() . ']';
            $requiredRole = implode(',', $requiredRoles);
            $actualRole = is_array($actualRoles) ? implode(',', $actualRoles) : '';

            AuditLog::create([
                'entity_type' => 'rbac_denial',
                'entity_id' => $actorIdStr,
                'action' => $attemptedRoute,
                'old_value' => null,
                'new_value' => json_encode([
                    'attempted_route' => $attemptedRoute,
                    'required_role' => $requiredRole,
                    'user_role' => $actualRole,
                ]),
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
                'created_at' => now(),
            ]);

            if ($userId) {
                $count = DB::table('audit_log')
                    ->where('entity_type', 'rbac_denial')
                    ->where('entity_id', $actorIdStr)
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count();
                if ($count >= 5) {
                    AuditLog::create([
                        'entity_type' => 'rbac_alert',
                        'entity_id' => $actorIdStr,
                        'action' => 'admin_alert',
                        'old_value' => null,
                        'new_value' => json_encode([
                            'denial_count' => $count,
                            'window_hours' => 24,
                            'user_id' => $actorIdStr,
                        ]),
                        'user_id' => null,
                        'ip_address' => $request->ip(),
                        'timestamp' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Fail-soft: do not break 403 response
        }
    }
}

<?php
namespace App\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use App\Utils\ResponseFormatter;

class AuthController {
    public function login(Request $request) {
        // SOURCE: openapi.yaml /auth/login — request keys: username, password
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
        $username = trim((string) $credentials['username']);
        $password = (string) $credentials['password'];

        // SOURCE: openapi.yaml LoginResponse — username can map to username column or email fallback.
        $hasUsernameColumn = Schema::hasColumn('users', 'username');
        $userQuery = User::query();
        if ($hasUsernameColumn) {
            $userQuery->where('username', $username);
        } else {
            $userQuery->where('email', $username);
        }
        $user = $userQuery->first();
        
        if (!$user || !Hash::check($password, $user->password_hash)) {
            // SOURCE: CLAUDE.md §10; openapi.yaml /auth/login 401
            // FIX: API-16 — use shared error helper
            return ResponseFormatter::standardError(401, 'INVALID_CREDENTIALS', 'Invalid credentials');
        }
        
        // Custom token: encrypted payload so AuthMiddleware can resolve the correct user
        $tokenPayload = json_encode(['user_id' => $user->id]);
        $token = Crypt::encryptString($tokenPayload);
        
        // Get ALL roles for the user (writers may have CONTENT_EDITOR + PRODUCT_SPECIALIST)
        $userRoleNames = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->values()
            ->all();
        
        // If no roles in DB (e.g. seed not run), infer from known seed emails so writer/reviewer/admin can still access
        $writerEmails = ['writer@cie.internal.com'];
        $reviewerEmails = ['kpi_reviewer@cie.internal.com'];
        $adminEmails = ['admin@cie.internal.com'];
        $email = strtolower(trim($user->email ?? ''));
        if (count($userRoleNames) === 0) {
            if (in_array($email, $writerEmails)) {
                $userRoleNames = ['CONTENT_EDITOR', 'PRODUCT_SPECIALIST'];
            } elseif (in_array($email, $reviewerEmails)) {
                $userRoleNames = ['CONTENT_LEAD', 'SEO_GOVERNOR'];
            } elseif (in_array($email, $adminEmails)) {
                $userRoleNames = ['ADMIN'];
            }
        }
        
        $redirectTo = '/writer/queue';
        if (in_array('ADMIN', $userRoleNames, true)) {
            $redirectTo = '/admin/clusters';
        } elseif (in_array('CONTENT_LEAD', $userRoleNames, true) || in_array('SEO_GOVERNOR', $userRoleNames, true)) {
            $redirectTo = '/review/dashboard';
        }

        // SOURCE: openapi.yaml LoginResponse schema — direct root payload, no envelope.
        return response()->json([
            'token' => $token,
            'user_id' => (string) $user->id,
            'roles' => array_values($userRoleNames),
            'redirect_to' => $redirectTo,
        ], 200);
    }

    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $nameParts = explode(' ', trim($request->input('name')), 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->input('email'),
            'password_hash' => Hash::make($request->input('password')),
        ]);

        $roleName = strtoupper($request->input('role', 'VIEWER'));
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
        }

        $tokenPayload = json_encode(['user_id' => $user->id]);
        $token = Crypt::encryptString($tokenPayload);

        return response()->json([
            'token' => $token,
            'user_id' => (string) $user->id,
            'roles' => [$roleName],
            'redirect_to' => '/writer/queue',
        ], 201);
    }
}

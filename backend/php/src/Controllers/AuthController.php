<?php
namespace App\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AuthController {
    public function register(Request $request) {
        $allowedRoles = [
            'content_editor', 'product_specialist', 'seo_governor', 'channel_manager',
            'ai_ops', 'content_lead', 'portfolio_holder', 'finance', 'admin', 'system', 'viewer',
            'editor', 'governor', 'analyst'  // legacy
        ];

        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|in:' . implode(',', $allowedRoles)
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }
        
        try {
            // Split full name into first and last name
            $nameParts = explode(' ', trim($request->input('name')), 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';
            
            // Map frontend roles (snake_case) to database role names (UPPERCASE)
            $roleMapping = [
                'content_editor' => 'CONTENT_EDITOR',
                'product_specialist' => 'PRODUCT_SPECIALIST',
                'seo_governor' => 'SEO_GOVERNOR',
                'channel_manager' => 'CHANNEL_MANAGER',
                'ai_ops' => 'AI_OPS',
                'content_lead' => 'CONTENT_LEAD',
                'portfolio_holder' => 'CONTENT_LEAD',
                'finance' => 'FINANCE',
                'admin' => 'ADMIN',
                'system' => 'SYSTEM',
                'viewer' => 'VIEWER',
                'editor' => 'CONTENT_EDITOR',
                'governor' => 'SEO_GOVERNOR',
                'analyst' => 'AI_OPS',
            ];
            
            $inputRole = strtolower(trim($request->input('role')));
            $roleName = $roleMapping[$inputRole] ?? strtoupper(str_replace('-', '_', $inputRole));
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['id' => Str::uuid()->toString()]
            );
            
            // Create user with explicit ID generation
            $userId = Str::uuid()->toString();
            $user = new User();
            $user->id = $userId;
            $user->email = $request->input('email');
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->password_hash = Hash::make($request->input('password'));
            $user->is_active = true;
            $user->save();
            
            // Assign role through user_roles table
            if ($user && $role) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'assigned_at' => now(),
                ]);
            }
            
            // Refresh user to get all attributes
            $user->refresh();
            
            // Generate token that encodes this user so API requests resolve to the same user
            $tokenPayload = json_encode(['user_id' => $userId]);
            $token = Crypt::encryptString($tokenPayload);
            
            // Return user with name field for frontend
            $userArray = $user->toArray();
            $userArray['name'] = $user->first_name . ' ' . $user->last_name;
            $userArray['role'] = $roleName;
            
            return ResponseFormatter::format([
                'user' => $userArray,
                'token' => $token,
                'message' => 'Account created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function login(Request $request) {
        $user = User::where('email', $request->input('email'))->first();
        
        if (!$user || !Hash::check($request->input('password'), $user->password_hash)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
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
        $writerEmails = ['writer@cie.example.com'];
        $reviewerEmails = ['reviewer@cie.example.com'];
        $adminEmails = ['admin@cie.example.com'];
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
        
        // Primary role for backward compatibility: prefer writer/reviewer/admin for routing
        $primaryRole = 'VIEWER';
        if (count($userRoleNames) > 0) {
            $prefer = ['CONTENT_EDITOR', 'PRODUCT_SPECIALIST', 'CONTENT_LEAD', 'PORTFOLIO_HOLDER', 'SEO_GOVERNOR', 'ADMIN'];
            foreach ($prefer as $r) {
                if (in_array($r, $userRoleNames)) {
                    $primaryRole = $r;
                    break;
                }
            }
            if ($primaryRole === 'VIEWER') {
                $primaryRole = $userRoleNames[0];
            }
        }
        
        // Return user with name and roles so frontend can resolve writer/reviewer correctly
        $userArray = $user->toArray();
        $userArray['name'] = $user->first_name . ' ' . $user->last_name;
        $userArray['role'] = $primaryRole;
        $userArray['roles'] = $userRoleNames;
        
        return ResponseFormatter::format([
            'user' => $userArray,
            'token' => $token
        ]);
    }
}

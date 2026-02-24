<?php
namespace App\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
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
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            
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
        
        // Custom token generation for this standalone setup
        $token = bin2hex(random_bytes(32));
        
        // Get user's role
        $userRole = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->select('roles.name')
            ->first();
        
        // Return user with name field for frontend
        $userArray = $user->toArray();
        $userArray['name'] = $user->first_name . ' ' . $user->last_name;
        $userArray['role'] = $userRole ? $userRole->name : 'VIEWER';
        
        return ResponseFormatter::format([
            'user' => $userArray,
            'token' => $token
        ]);
    }
}

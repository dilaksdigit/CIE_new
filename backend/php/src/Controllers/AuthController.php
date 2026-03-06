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

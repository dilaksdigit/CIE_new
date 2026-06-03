<?php
// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.4 — Admin user management (no public self-registration)
namespace App\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController
{
    /**
     * SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.4 — list users for admin screen.
     */
    public function index()
    {
        $users = User::query()
            ->orderBy('email')
            ->get()
            ->map(function (User $user) {
                $roleNames = DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('user_roles.user_id', $user->id)
                    ->pluck('roles.name')
                    ->values()
                    ->all();

                return [
                    'id' => (string) $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'roles' => $roleNames,
                    'is_active' => (bool) ($user->is_active ?? true),
                    'created_at' => $user->created_at,
                ];
            });

        return ResponseFormatter::format($users, 'Users retrieved');
    }

    /**
     * SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.4 — admin creates accounts only.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error('Validation failed', 422, $validator->errors()->toArray());
        }

        $roleName = strtoupper(trim((string) $request->input('role')));
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return ResponseFormatter::standardError(422, 'INVALID_ROLE', 'Role not found');
        }

        $userId = (string) Str::uuid();
        $user = User::create([
            'id' => $userId,
            'email' => $request->input('email'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name', ''),
            'password_hash' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return ResponseFormatter::format([
            'id' => (string) $user->id,
            'email' => $user->email,
            'roles' => [$roleName],
        ], 'User created', 201);
    }

    /**
     * SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.4 — deactivate user (is_active=false).
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseFormatter::standardError(404, 'USER_NOT_FOUND', 'User not found');
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'sometimes|boolean',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'role' => 'sometimes|string',
            'password' => 'sometimes|string|min:8',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error('Validation failed', 422, $validator->errors()->toArray());
        }

        if ($request->has('is_active')) {
            $user->is_active = (bool) $request->input('is_active');
        }
        if ($request->has('first_name')) {
            $user->first_name = $request->input('first_name');
        }
        if ($request->has('last_name')) {
            $user->last_name = $request->input('last_name');
        }
        if ($request->filled('password')) {
            $user->password_hash = Hash::make($request->input('password'));
        }
        $user->save();

        if ($request->filled('role')) {
            $roleName = strtoupper(trim((string) $request->input('role')));
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                DB::table('user_roles')->where('user_id', $user->id)->delete();
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ]);
            }
        }

        return ResponseFormatter::format(['id' => (string) $user->id], 'User updated');
    }
}

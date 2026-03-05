<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SOURCE: README_First_CIE_v232_Developer_README.docx Phase 2;
 *         CIE_v232_Developer_Amendment_Pack_v2.docx Section 3.2
 * Seeds writer and reviewer accounts for CIE v2.3.2.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = 'writer123';

        $writer = User::where('email', 'writer@cie.internal')->first();
        if (!$writer) {
            $writer = new User();
            $writer->id = Str::uuid()->toString();
            $writer->email = 'writer@cie.internal';
            $writer->first_name = 'Writer';
            $writer->last_name = '';
            $writer->password_hash = Hash::make($defaultPassword);
            $writer->is_active = true;
            $writer->save();

            $roles = ['CONTENT_EDITOR', 'PRODUCT_SPECIALIST'];
            foreach ($roles as $roleName) {
                $role = Role::firstOrCreate(
                    ['name' => $roleName],
                    ['id' => Str::uuid()->toString()]
                );
                if (!DB::table('user_roles')->where('user_id', $writer->id)->where('role_id', $role->id)->exists()) {
                    DB::table('user_roles')->insert([
                        'user_id' => $writer->id,
                        'role_id' => $role->id,
                        'assigned_at' => now(),
                    ]);
                }
            }
        }

        $reviewer = User::where('email', 'kpi_reviewer@cie.internal')->first();
        if (!$reviewer) {
            $reviewer = new User();
            $reviewer->id = Str::uuid()->toString();
            $reviewer->email = 'kpi_reviewer@cie.internal';
            $reviewer->first_name = 'KPI Reviewer';
            $reviewer->last_name = '';
            $reviewer->password_hash = Hash::make($defaultPassword);
            $reviewer->is_active = true;
            $reviewer->save();

            $roles = ['CONTENT_LEAD', 'SEO_GOVERNOR'];
            foreach ($roles as $roleName) {
                $role = Role::firstOrCreate(
                    ['name' => $roleName],
                    ['id' => Str::uuid()->toString()]
                );
                if (!DB::table('user_roles')->where('user_id', $reviewer->id)->where('role_id', $role->id)->exists()) {
                    DB::table('user_roles')->insert([
                        'user_id' => $reviewer->id,
                        'role_id' => $role->id,
                        'assigned_at' => now(),
                    ]);
                }
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrator that runs all CIE seeders.
 * SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx Section 3.2
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}

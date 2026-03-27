<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * // SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.2 — sku_master.erp_margin_pct
     * // ADDITIVE ONLY — no column drops, no renames
     */
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->decimal('erp_margin_pct', 5, 2)
                ->nullable()
                ->default(null)
                ->after('erp_return_rate_pct')
                ->comment('From ERP sync. Used in tier calculation.');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn('erp_margin_pct');
        });
    }
};


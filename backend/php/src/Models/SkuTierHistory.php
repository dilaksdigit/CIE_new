<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// SOURCE: CIE_v231_Developer_Build_Pack.pdf §1.2 — sku_tier_history
// Columns: approved_by, second_approver (spec); changed_by (additive extension via migration 114)
class SkuTierHistory extends Model
{
    protected $table = 'sku_tier_history';

    public $timestamps = false;

    protected $guarded = [];
}

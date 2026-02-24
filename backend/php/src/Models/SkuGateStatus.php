<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuGateStatus extends Model
{
    protected $table = 'sku_gate_status';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
}


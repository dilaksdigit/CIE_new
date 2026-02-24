<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffEffortLog extends Model
{
    protected $table = 'staff_effort_logs';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}

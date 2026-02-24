<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TierHistory extends Model
{
    protected $table = 'tier_history';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'old_tier' => \App\Enums\TierType::class,
        'new_tier' => \App\Enums\TierType::class,
        'changed_at' => 'datetime',
    ];
}


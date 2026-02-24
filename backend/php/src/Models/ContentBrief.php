<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBrief extends Model
{
    protected $table = 'content_briefs';
    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'suggested_actions' => 'array',
        'deadline' => 'date',
    ];

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}

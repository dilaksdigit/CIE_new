<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sku extends Model
{
    protected $table = 'skus';
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Sku $sku) {
            if (empty($sku->getAttribute($sku->getKeyName()))) {
                $sku->setAttribute($sku->getKeyName(), (string) Str::uuid());
            }
        });
    }

    protected $casts = [
        'validation_status' => \App\Enums\ValidationStatus::class,
    ];

    public function primaryCluster()
    {
        return $this->belongsTo(Cluster::class, 'primary_cluster_id');
    }

    public function skuIntents()
    {
        return $this->hasMany(SkuIntent::class);
    }

    public function secondaryIntents()
    {
        return $this->hasMany(SkuIntent::class)->where('is_primary', false);
    }

    public function auditResults()
    {
        return $this->hasMany(AuditResult::class, 'sku_id');
    }

    public function validationLogs()
    {
        return $this->hasMany(ValidationLog::class, 'sku_id');
    }
}

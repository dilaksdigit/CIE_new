<?php

namespace App\Models;

use App\Enums\TierType;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1; CLAUDE.md Section 9
    // Uses Attribute accessor instead of $casts to handle mixed-case DB values
    // (e.g. 'HERO' vs 'hero') — TierType::tryFrom(strtolower()) is case-safe.
    protected function tier(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? TierType::tryFrom(strtolower($value)) : null,
            set: fn ($value) => $value instanceof TierType ? $value->value : ($value !== null ? strtolower($value) : null),
        );
    }

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

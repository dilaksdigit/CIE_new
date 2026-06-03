<?php

namespace App\Models;

use App\Enums\TierType;
use App\Enums\ValidationStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * SOURCE: CLAUDE.md §9 — tier ENUM lowercase; normalizes DB strings before TierType cast
 */
final class SkuTierCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?TierType
    {
        if ($value === null || $value === '') {
            return null;
        }

        return TierType::tryFrom(strtolower((string) $value));
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        if ($value === null) {
            return ['tier' => null];
        }
        if ($value instanceof TierType) {
            return ['tier' => $value->value];
        }

        return ['tier' => strtolower((string) $value)];
    }
}

/**
 * SOURCE: openapi status values include mixed-case 'pending'; tolerate legacy casing in DB.
 */
final class SkuValidationStatusCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?ValidationStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (strtolower($raw) === ValidationStatus::PENDING->value) {
            return ValidationStatus::PENDING;
        }

        return ValidationStatus::tryFrom(strtoupper($raw));
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        if ($value === null) {
            return ['validation_status' => null];
        }
        if ($value instanceof ValidationStatus) {
            return ['validation_status' => $value->value];
        }

        $raw = trim((string) $value);
        if (strtolower($raw) === ValidationStatus::PENDING->value) {
            return ['validation_status' => ValidationStatus::PENDING->value];
        }

        return ['validation_status' => strtoupper($raw)];
    }
}

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
        'validation_status' => SkuValidationStatusCast::class,
        // SOURCE: CLAUDE.md §9 — tier stored as ENUM; SkuTierCast normalizes case for tryFrom
        'tier' => SkuTierCast::class,
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

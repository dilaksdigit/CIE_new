<?php

namespace App\Models;

use App\Support\BusinessRules;
use Illuminate\Database\Eloquent\Model;

/**
 * CIE v2.3.2 – business_rules table. Cache is invalidated after every rule update.
 */
class BusinessRule extends Model
{
    protected $table = 'business_rules';

    protected $primaryKey = 'rule_key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['rule_key', 'rule_value', 'data_type', 'module', 'label', 'description', 'approver_roles'];

    protected static function booted(): void
    {
        static::saved(function () {
            BusinessRules::invalidateCache();
        });
    }
}

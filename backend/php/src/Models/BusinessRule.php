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

    protected $fillable = ['rule_key', 'value', 'value_type', 'description'];

    protected static function booted(): void
    {
        static::saved(function () {
            BusinessRules::invalidateCache();
        });
    }
}

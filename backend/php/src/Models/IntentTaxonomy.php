<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntentTaxonomy extends Model
{
    protected $table = 'intent_taxonomy';
    protected $guarded = [];
    public $timestamps = true;

    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 — Primary Intent from locked taxonomy (normalized intent_key list)
    /** @return list<string> */
    public static function validPrimaryIntents(): array
    {
        return static::query()
            ->orderBy('intent_id')
            ->pluck('intent_key')
            ->map(fn ($k) => strtolower(str_replace([' ', '-', '/'], '_', (string) $k)))
            ->values()
            ->all();
    }
}


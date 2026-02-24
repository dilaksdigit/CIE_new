<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditResult extends Model
{
    protected $table = 'audit_results';
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $guarded = [];
    
    protected $casts = [
        'score' => 'integer',
    ];

    /**
     * Relationship: Audit result belongs to a SKU
     */
    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    /**
     * Get results by engine
     */
    public static function byEngine($engine)
    {
        return self::where('engine_type', $engine);
    }

    /**
     * Get all results for a SKU
     */
    public static function forSku($skuId)
    {
        return self::where('sku_id', $skuId)->orderByDesc('queried_at');
    }
}

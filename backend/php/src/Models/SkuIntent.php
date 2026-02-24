<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuIntent extends Model
{
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;

    public function sku() { return $this->belongsTo(Sku::class); }
    public function intent() { return $this->belongsTo(Intent::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}

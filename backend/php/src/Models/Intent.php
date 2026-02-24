<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Intent extends Model
{
  protected $guarded = [];
  protected $keyType = 'string';
  public $incrementing = false;
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

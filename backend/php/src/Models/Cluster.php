<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    protected $table = 'clusters';
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;

    public function skus()
    {
        return $this->hasMany(Sku::class, 'primary_cluster_id');
    }
}

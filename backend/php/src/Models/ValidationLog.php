<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ValidationLog extends Model
{
 protected $guarded = [];
 public function sku() { return $this->belongsTo(Sku::class); }
}

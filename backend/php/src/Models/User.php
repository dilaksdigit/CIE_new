<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'users';
    protected $fillable = ['id', 'email', 'first_name', 'last_name', 'password_hash', 'is_active'];
    protected $hidden = ['password_hash'];
    protected $appends = ['name'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}

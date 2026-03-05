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

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Primary role for backward compatibility (first of user's roles).
     * Schema uses user_roles (many-to-many); this exposes a single role for code that uses $user->role.
     */
    public function getRoleAttribute()
    {
        if ($this->relationLoaded('roles') && $this->roles->isNotEmpty()) {
            return $this->roles->first();
        }
        $this->loadMissing('roles');
        return $this->roles->first();
    }
    
    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}

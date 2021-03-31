<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;

// JWT Auth
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable, HasFactory;
    
    protected $primaryKey = 'userId';

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email',
    ];
    
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'pwd', 'createdAt', 'updatedAt', 'createdBy'
    ];
    
    public function getAuthPassword()
    {
        return $this->pwd;
    }
    
    public static function index() {
        if (auth()->user()->isYeltechAdmin()) {
            return User::all();
        } else {
            return User::where('groupId', auth()->user()->groupId)->get();
        }
    }

    public function group() {
        return $this->belongsTo(Group::class, 'groupId', 'groupId');
    }

    public function isSuperAdmin() {
        if ( $this->roleId == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function isYeltechAdmin() {
        if ( $this->roleId == 1 || $this->roleId == 2) {
            return true;
        } else {
            return false;
        }
    }

    public function isGroupAdmin() {
        if ( $this->roleId == 1 || $this->roleId == 2 || $this->roleId == 3) {
            return true;
        } else {
            return false;
        }
    }

    public function hasAppAccess() {
        return $this->group->appAccess;
        if ( $this->group->appAccess == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}

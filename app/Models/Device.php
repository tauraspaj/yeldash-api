<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use DB;

class Device extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory;

    protected $primaryKey = 'deviceId';
    const UPDATED_AT = 'updatedAt';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'deviceAlias', 'customLocation', 'lastCalibration', 'nextCalibrationDue'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'createdBy', 'createdAt', 'updatedAt', 'deviceTypeId', 'productId', 'groupId'
    ];

    public static function index() {
        if (auth()->user()->isYeltechAdmin()) {
            return Device::all();
        } else {
            return Device::where('groupId', auth()->user()->groupId)->get();
        }
    }

    public function group() {
        return $this->belongsTo(Group::class, 'groupId', 'groupId');
    }

    public function channels() {
        return $this->hasMany(Channel::class, 'deviceId', 'deviceId');
    }

    public function alarmTriggers() {
        return $this->hasMany(AlarmTrigger::class, 'deviceId', 'deviceId');
    }

    public function alarmRecipients() {
        $result = DB::table('alarmRecipients')
                    ->select('alarmRecipients.userId', 'alarmRecipients.alarmRecipientId', 'users.fullName', 'users.email')
                    ->leftJoin('users', 'alarmRecipients.userId', '=', 'users.userId')
                    ->where('deviceId', $this->deviceId)
                    ->orderBy('users.roleId', 'ASC')
                    ->orderBy('users.fullName', 'ASC')
                    ->get();
        return $result;
    }
}

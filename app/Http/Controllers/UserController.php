<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    public function index() {
        return User::index();
    }

    public function deviceList() {
        $devices = auth()->user()->devices();
        return $devices;
        
        $result = array();
        $i = 0;
        foreach($devices as $device) {
            $result[$i] = $device;
            
            if ($device->alarmTriggers()->find(1) != null) {
                try {
                    $result[$i]['isTriggered'] = $device->alarmTriggers()->where('isTriggered', 1)->get()[0]->isTriggered;
                } catch(\Exception $e) {
                    $result[$i]['isTriggered'] = 0;
                }
            } else {
                $result[$i]['isTriggered'] = 0;
            }
            $i++;
        }
        return response()->json($result, 200);
    }
}



<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\User;
use DB;

class DeviceController extends Controller
{
    public function deviceList() {
        $devices = Device::index();

        // Check for no devices
        if (count($devices) == 0) {
            return response()->json(['error' => 'Your group does not have any registered devices'], 200);
        }

        $result = array();
        $i = 0;
        foreach($devices as $device) {
            $result[$i] = $device;
            $result[$i]['numberOfTriggeredAlarms'] = $device->alarmTriggers()->where('isTriggered', 1)->count();

            $i++;
        }
        return response()->json($result, 200);
    }

    public function show($deviceId) {
        $device = Device::find($deviceId);

        $result = array();

        // Device data
        $result['deviceData'] = $device;
        // Subscription data
        $result['subscription'] = DB::table('subscriptions')->where('deviceId', $device->deviceId)->select('subStart', 'subFinish')->get();

        $channels = $device->channels()->where('channelType', 'AI')->get();
        $result['channels'] = array();
        
        $i = 0;
        foreach ($channels as $channel) {
            // Channel data
            // $result['channels'][$i] = $channel;

            // Measurements log for each channel for the last 3 hours
            $now = date('Y-m-d H:i:s', time());
            $hours3 = date('Y-m-d H:i:s', time() - 3*60*60);
            $result['channels'][$i]['measurements'] = DB::table('measurements')
                                                    ->select('measurements.measurement', 'measurements.measurementTime', 'channels.channelName', 'units.unitName as unit')
                                                    ->whereBetween('measurements.measurementTime', [$hours3, $now])
                                                    ->where('measurements.channelId', $channel->channelId)
                                                    ->leftJoin('channels', 'measurements.channelId', '=', 'channels.channelId')
                                                    ->leftJoin('units', 'channels.unitId', '=', 'units.unitId')
                                                    ->orderBy('measurements.measurementTime', 'DESC')
                                                    ->get();
            $i++;
        }

        // Get alarm triggers
        $result['alarmTriggers'] = $device->alarmTriggers()->select('triggerId', 'operator', 'thresholdValue', 'isTriggered')->get();

        // Get group users
        $result['groupUsers'] = User::where('groupId', $device->groupId)->select('userId', 'fullName', 'email')->get();
        
        // Get assigned alarm recipients
        $result['alarmRecipients'] = $device->alarmRecipients();

        $first = DB::table('smsAlarms')
                    ->select('channels.channelName AS channelName', 'smsAlarms.smsAlarmHeader AS col1', 'smsAlarms.smsAlarmReading AS col2', DB::raw('"SYSTEM" AS clearedBy'), 'smsAlarms.smsAlarmTime AS timestampCol')
                    ->leftJoin('channels', 'smsAlarms.channelId', '=', 'channels.channelId')
                    ->where('smsAlarms.deviceId', $device->deviceId);
                    
        $second = DB::table('triggeredAlarmsHistory')
                    ->select('channels.channelName AS channelName', 'alarmTriggers.operator', 'alarmTriggers.thresholdValue', 'users.fullName as clearedBy', 'triggeredAlarmsHistory.clearedAt')
                    ->leftJoin('alarmTriggers', 'triggeredAlarmsHistory.triggerId', '=', 'alarmTriggers.triggerId')
                    ->leftJoin('channels', 'alarmTriggers.channelId', '=', 'channels.channelId')
                    ->leftJoin('users', 'triggeredAlarmsHistory.clearedBy', '=', 'users.userId')
                    ->where('alarmTriggers.deviceId', $device->deviceId);
                    
        $third = DB::table('smsStatus')
                    ->select(DB::raw('"Device" AS channelName'), 'smsStatus.smsStatus', DB::raw('NULL AS col2'), DB::raw('"SYSTEM" AS clearedBy'), 'smsStatus.smsStatusTime AS timestampCol')
                    ->where('smsStatus.deviceId', $device->deviceId);
                    

        $result['alarmHistory'] = $first->union($second)->union($third)->orderBy('timestampCol', 'DESC')->take(10)->get();

        return response()->json($result, 200);
    }
}

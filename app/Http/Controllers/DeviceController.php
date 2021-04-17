<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
        $result['deviceData']['latitude'] = '63.446114';
        $result['deviceData']['longitude'] = '10.899592';
        // Subscription data
        $result['subscription'] = DB::table('subscriptions')->where('deviceId', $device->deviceId)->select('subStart', 'subFinish')->get();

        $channels = $device->channels()->where('channelType', 'AI')->get();
        $result['channels'] = array();
        
        $i = 0;
        foreach ($channels as $channel) {
            $result['channels'][$i]['chart']['labels'] = array();
            $result['channels'][$i]['chart']['dataset'] = array();
            // Channel data
            // $result['channels'][$i]['channelData'] = $channel;

            // Last 10 measurements log
            $result['channels'][$i]['measurements'] = DB::table('measurements')
                                                    ->select('measurements.measurement', 'measurements.measurementTime', 'channels.channelName', 'units.unitName as unit')
                                                    // ->whereBetween('measurements.measurementTime', [$hours3, $now])
                                                    ->where('measurements.channelId', $channel->channelId)
                                                    ->leftJoin('channels', 'measurements.channelId', '=', 'channels.channelId')
                                                    ->leftJoin('units', 'channels.unitId', '=', 'units.unitId')
                                                    ->orderBy('measurements.measurementTime', 'DESC')
                                                    ->limit(10)
                                                    ->get();

            // Generate chart display [labels] and [dataset]
            // Data from the last 3 hours and limited to 8 readings
            $now = date('Y-m-d H:i:s', time());
            $hours3 = date('Y-m-d H:i:s', time() - 4000*3*60*60);
            $chart = DB::table('measurements')
                        ->select('measurements.measurement', 'measurements.measurementTime', 'channels.channelName', 'units.unitName as unit')
                        ->whereBetween('measurements.measurementTime', [$hours3, $now])
                        ->where('measurements.channelId', $channel->channelId)
                        ->leftJoin('channels', 'measurements.channelId', '=', 'channels.channelId')
                        ->leftJoin('units', 'channels.unitId', '=', 'units.unitId')
                        ->orderBy('measurements.measurementTime', 'DESC')
                        ->limit(8)
                        ->get();
            // $result['channels'][$i]['chart'] = $chart;

            for ($j = 0; $j < count($chart); $j++) {
                // $result['channels'][$i]['chart'][$j]['labels'] = $chart[$j]->measurement;
                array_push($result['channels'][$i]['chart']['labels'], substr($chart[$j]->measurementTime, 11, -3));
                array_push($result['channels'][$i]['chart']['dataset'], $chart[$j]->measurement);
            }
            // foreach ($chart as $row) {
                // array_push($result['channels'][$i]['chart']['labels'], $row);
            // }
            $i++;
        }


        // Get alarm triggers
        $result['alarmTriggers'] = $device->alarmTriggers()
                                            ->select('alarmTriggers.triggerId', 'alarmTriggers.operator', 'alarmTriggers.thresholdValue', 'alarmTriggers.isTriggered', 'channels.channelName')
                                            ->leftJoin('channels', 'alarmTriggers.channelId', '=', 'channels.channelId')
                                            ->get();

        // Get group users
        $groupUsers = User::where('groupId', $device->groupId)->select('userId', 'fullName', 'email')->get();
        
        // Get assigned alarm recipients
        $alarmRecipients = $device->alarmRecipients();

        foreach ($groupUsers as $groupUser) {
            $groupUser['isRecipient'] = 0;
            foreach ($alarmRecipients as $recipient) {
                if ($recipient->userId == $groupUser->userId) {
                    $groupUser['isRecipient'] = 1;
                }
            }
        }
        $result['deviceRecipients'] = $groupUsers;

        $first = DB::table('smsAlarms')
                    ->select('channels.channelName AS channelName', 'smsAlarms.smsAlarmHeader AS msg1', 'smsAlarms.smsAlarmReading AS msg2', DB::raw('"SYSTEM" AS clearedBy'), 'smsAlarms.smsAlarmTime AS timestampCol')
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
                    

        $result['alarmHistory'] = $first->union($second)->union($third)->orderBy('timestampCol', 'DESC')->limit(10)->get();

        return response()->json($result, 200);
    }

    public function toggleRecipient($deviceId, Request $request) {
        $device = Device::find($deviceId);

        $userId = $request->userId;
        $newRecipientState = $request->newRecipientState;

        $oldRecipientState = $device->alarmRecipients()->where('userId', $userId)->count();

        if ($newRecipientState == 0 && $oldRecipientState == 1) {
            if ( DB::table('alarmRecipients')->where('userId', $userId)->delete() ) {
                $status = 200;
                $output = [
                    'message' => 'User recipient state changed'
                ];
            } else {
                $status = 400;
                $output = [
                    'message' => 'Something went wrong'
                ];
            }
        } else if ($newRecipientState == 1 && $oldRecipientState == 0) {
            if ( DB::table('alarmRecipients')->insert([['deviceId' => $device->deviceId, 'userId' => $userId]]) ) {
                $status = 200;
                $output = [
                    'message' => 'User recipient state changed'
                ];
            } else {
                $status = 400;
                $output = [
                    'message' => 'Something went wrong'
                ];
            }
        } else {
            $status = 200;
            $output = [
                'message' => 'No change in recipient state detected'
            ];
        }

        return response()->json($output, $status);
    }

    public function updateCustomise($deviceId, Request $request) {
        $device = Device::find($deviceId);

        $device->deviceAlias = $request['deviceAlias'];
        $device->customLocation = $request['customLocation'];

        // Update device
        if ( $device->save() ) {
            $status = 200;
            $output = [
                'user' => $device,
                'message' => 'Device updated successfully'
            ];
        } else {
            $status = 500;
            $output = [
                'message' => 'An error occured while updating device'
            ];
        }

        return response()->json($output, $status);
    }
}

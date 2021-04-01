<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;

class UserController extends Controller
{
    public function index() {
        return response()->json(User::index(), 200);
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

    public function myProfile() {
        $user = auth()->user();
        return response()->json($user, 200);
    }

    public function update(Request $request) {
        $user = auth()->user();

        $this->validate($request, [
            'fullName' => 'required|string',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->userId, 'userId')],
            'phoneNumber' => ['required', 'min:7', Rule::unique('users')->ignore($user->userId, 'userId')],
            'pwd' => 'required|min:6'
        ]);

        // ! Find how to update sending type id
        $user->fullName = $request['fullName'];
        $user->email = $request['email'];
        $user->phoneNumber = $request['phoneNumber'];
        $user->pwd = app('hash')->make($request['pwd']);

        $user->save();

        // Update user
        if ( $user->save() ) {
            $status = 200;
            $output = [
                'user' => $user,
                'message' => 'User updated successfully'
            ];
        } else {
            $status = 500;
            $output = [
                'message' => 'An error occured while up[dating user'
            ];
        }

        return response()->json($output, $status);
    }

    public function create(Request $request) {
        if ( !auth()->user()->isGroupAdmin() )
            return response()->json(['message' => 'Unauthorized'], 401);

        // ! Find how to set role id
        $this->validate($request, [
            'fullName' => 'required|string',
            'email' => 'required|email|unique:users',
            'phoneNumber' => 'required|min:7|unique:users',
            'pwd' => 'required|min:6'
        ]);

        $user = new User;
        $user->fullName = $request['fullName'];
        $user->email = $request['email'];
        $user->phoneNumber = $request['phoneNumber'];
        $user->pwd = app('hash')->make($request['pwd']);

        $user->groupId = auth()->user()->groupId;
        $user->roleId = 4;
        $user->createdBy = auth()->user()->userId;

        // Save user
        if ( $user->save() ) {
            $status = 200;
            $output = [
                'user' => $user,
                'message' => 'User created successfully'
            ];
        } else {
            $status = 500;
            $output = [
                'message' => 'An error occured while creating user'
            ];
        }

        return response()->json($output, $status);
    }
}



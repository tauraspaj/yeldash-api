<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Device;

class DeviceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $device = Device::find($request->deviceId);

        if ($device == null) {
            return response()->json([
                'error' => 'Device not found'
            ], 401);
        }
        
        if (auth()->user()->isYeltechAdmin() || auth()->user()->groupId == $device->groupId)
            return $next($request);

        return response()->json([
            'error' => 'Unauthorized'
        ], 401);
        
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;

class DevicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.basic.once');
    }

    public function upsert(DeviceRequest $request)
    {
        $user = $request->user();
        $deviceAttributes = $request->getDeviceAttributes();
        $device = $user->devices()->whereDeviceId($deviceAttributes['device_id'])->first();

        if (!$device) {
            $device = $user->devices()->create($deviceAttributes);
        } else {
            $device->update($deviceAttributes);
        }

        return $device;
    }
}

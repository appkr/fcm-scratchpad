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

        $device = $user->devices()
            ->whereDeviceId($request->device_id)->first();

        $input = $request->getInput();

        if (!$device) {
            $device = $user->devices()->create($input);
        } else {
            $device->update($input);
        }

        return $device;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;

class DevicesController extends Controller
{
    /**
     * DevicesController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth.basic.once');
    }

    /**
     * 요청한 단말 모델이 없으면 새로 만들고, 있으면 업데이트합니다.
     *
     * @param DeviceRequest $request
     * @return mixed
     */
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

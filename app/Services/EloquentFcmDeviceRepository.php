<?php

namespace App\Services;

use App\Device;
use DB;

class EloquentFcmDeviceRepository implements FcmDeviceRepository
{
    public function updateFcmDevice(string $oldId, string $newId)
    {
        $device = Device::where('push_service_id', $oldId)->first();
        if ($device === null) {
            return;
        }

        $device->push_service_id = $newId;

        DB::transaction(function () use ($device) {
            $device->save();
        });
    }

    public function deleteFcmDevice(string $deprecatedId)
    {
        $device = Device::where('push_service_id', $deprecatedId)->first();
        if ($device === null) {
            return;
        }

        DB::transaction(function () use ($device) {
            $device->delete();
        });
    }
}

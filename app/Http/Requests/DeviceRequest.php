<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'device_id' => 'required',
            'os_enum' => 'required|in:ANDROID,IOS',
            'model' => 'string',
            'operator' => 'string',
            'api_level' => 'numeric',
            'push_service_enum' => 'in:FCM',
            'push_service_id' => 'size:152',
        ];
    }

    public function getDeviceAttributes()
    {
        return [
            'device_id' => $this->getValue('device_id'),
            'os_enum' => $this->getValue('os_enum'),
            'model' => $this->getValue('model'),
            'operator' => $this->getValue('operator'),
            'api_level' => $this->getValue('api_level'),
            'push_service_enum' => $this->getValue('push_service_enum', 'FCM'),
            'push_service_id' => $this->getValue('push_service_id'),
        ];
    }

    protected function getValue(string $key, $default = null, callable $filter = null)
    {
        $value = $this->exists($key) ? $this->input($key) : $default;
        $hasValidFilterGiven = ((null !== $filter) && is_callable($filter));
        if (is_array($value)) {
            $hasValidValueGiven = (! empty(array_filter($value)));
        } else {
            $hasValidValueGiven = (mb_strlen($value) > 0);
        }
        if ($hasValidFilterGiven && $hasValidValueGiven) {
            return call_user_func($filter, $value);
        }

        return $value;
    }
}

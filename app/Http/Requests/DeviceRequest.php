<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
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

    /**
     * @return array
     */
    public function getInput()
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

    /**
     * 입력값을 변환합니다.
     *
     * @param string $key
     * @param mixed|null $default
     * @param callable|null $filter
     * @return array|null|string
     */
    protected function getValue($key, $default = null, callable $filter = null)
    {
        $value = $this->has($key) ? $this->input($key) : $default;

        if (is_null($filter) || ! is_callable($filter)) {
            return $value;
        }

        return call_user_func($filter, [$value]);
    }
}

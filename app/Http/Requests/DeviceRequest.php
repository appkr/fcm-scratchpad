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
            'os_enum' => 'in:ANDROID',
            'model' => 'string',
            'operator' => 'string',
            'api_level' => 'numeric',
            'push_service_enum' => 'in:FCM',
            'push_service_id' => 'size:162',
        ];
    }
}

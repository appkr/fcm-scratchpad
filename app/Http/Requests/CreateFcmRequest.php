<?php

namespace App\Http\Requests;

use App\User;
use Illuminate\Foundation\Http\FormRequest;

class CreateFcmRequest extends FormRequest
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
            'user_id' => 'required',
            'first_field' => 'string',
            'second_field' => 'string',
        ];
    }

    /**
     * FCM을 수신할 단말기 목록을 추출합니다.
     *
     * @return array
     */
    public function getReceivers()
    {
        $user = User::findOrFail($this->get('user_id'));

        return $user->devices()->pluck('push_service_id')->toArray();
    }

    /**
     * FCM으로 전송할 메시지 본문을 조립합니다.
     *
     * @return array
     */
    public function getFcmPayload()
    {
        return $this->except('user_id', '_token', '_method');
    }
}

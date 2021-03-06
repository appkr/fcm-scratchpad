<?php

namespace App\Http\Requests;

use App\User;
use Illuminate\Foundation\Http\FormRequest;

class CreateFcmRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => 'required',
            'first_field' => 'string',
            'second_field' => 'string',
        ];
    }

    public function getReceivers()
    {
        /** @var User $user */
        $user = User::findOrFail($this->get('user_id'));

        return $user->getPushServiceIds();
    }

    public function getFcmMessage()
    {
        return $this->except('user_id', '_token', '_method');
    }
}

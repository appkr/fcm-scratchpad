<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFcmRequest;
use App\Services\FcmHandler;
use App\User;
use View;

class FcmController extends Controller
{
    public function create(User $user)
    {
        return View::make('fcm.create')->with('user_id', $user->id);
    }

    public function send(CreateFcmRequest $request, FcmHandler $fcmHandler)
    {
        $receivers = $request->getReceivers();
        $message = $request->getFcmMessage();

        if (! empty($receivers)) {
            $fcmHandler->setReceivers($receivers);
            $fcmHandler->setMessage($message);
            $fcmHandler->sendMessage();
        }

        return redirect(route('users.index'))->with(
            'response',
            empty($receivers)
                ? '메시지를 받을 단말기 목록이 없습니다.'
                : '메시지를 전송했습니다. 로그를 확인해주세요.'
        );
    }
}

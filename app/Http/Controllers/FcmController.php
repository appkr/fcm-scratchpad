<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFcmRequest;
use App\Services\FCMHandler;
use App\User;
use LaravelFCM\Response\DownstreamResponse;

class FcmController extends Controller
{
    /**
     * FCM으로 전송할 메시지를 입력받을 폼을 출력합니다.
     *
     * @param User $user
     * @return \Illuminate\Http\Response
     */
    public function create(User $user)
    {
        return view('fcm.create')->with('user_id', $user->id);
    }

    /**
     * FCM 메시지를 전송합니다.
     *
     * @param CreateFcmRequest $request
     * @param FCMHandler $fcm
     * @return \Illuminate\Http\Response
     */
    public function send(CreateFcmRequest $request, FCMHandler $fcm)
    {
        $to = $request->getReceivers();
        $data = $request->getFcmPayload();

        if (! empty($to)) {
            $response = $fcm->to($to)->data($data)->send();
        }

        return redirect(route('users.index'))->with(
            'response',
            empty($to)
                ? '메시지를 받을 단말기 목록이 없습니다.'
                : $this->buildFlashMessage($response)
        );
    }

    /**
     * FCM 전송 후 결과로 제시할 플래시 메시지를 조립합니다.
     *
     * @param DownstreamResponse $response
     * @return string
     */
    protected function buildFlashMessage(DownstreamResponse $response)
    {
        return sprintf("전송 성공: %d건 • 전송 실패: %d건 • 모델 업데이트: %d건 • 모델 삭제: %d건의 작업을 수행했습니다.",
            $response->numberSuccess(),
            $response->numberFailure(),
            $response->numberModification(),
            count($response->tokensToDelete())
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Events\UserUpdated;
use Illuminate\Http\Request;

class FooController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.basic.once');
    }

    public function bar(Request $request)
    {
        // 모델 변경 이벤트를 단말기에게 전달하는 상황을 가정합니다.
        // 예제 프로젝트에는 테스트할 모델이 없어서 User 모델을 사용합니다.
        $user = $request->user();

        // 이벤트를 던집니다. 이벤트 핸들러에서
        // FCM을 보내는 일 외에도 다른 일도 할 수도 있으므로 Job 보다는 이벤트가 더 유연합니다.
        event(new UserUpdated($user));

        return response()->json([
            'success' => 'HTTP 요청 처리 완료'
        ]);
    }
}

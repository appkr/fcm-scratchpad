<?php

namespace App\Listeners;

use App\Events\UserUpdated;
use App\Services\FcmHandler;
use Illuminate\Contracts\Queue\ShouldQueue;

// ShouldQueue라는 빈 인터페이스를 구현하면 이벤트를 처리하기 위해 작업 큐를 이용합니다.
// .env에 QUEUE_DRIVER가 설정되어 있고, artisan queue:work 명령이 실행되어 있어야 합니다.
// 큐는 작업을 수행할 객체를 직렬화해서 저장했다가, 이번 프로세스가 아닌 별도의 새로운 프로세스에서
// 작업을 실행합니다. 즉 사용자 요청과 연결된 프로세스의 응답 시간을 단축해 줍니다.
class WhenUserUpdated implements ShouldQueue
{
    /**
     * @var FcmHandler
     */
    private $fcm;

    public function __construct(FcmHandler $fcm)
    {
        $this->fcm = $fcm;
    }

    public function handle(UserUpdated $event)
    {
        $user = $event->getUser();
        $receivers = $user->getPushServiceIds();

        if (! empty($receivers)) {
            $message = array_merge(
                $user->toArray(),
                ['foo' => 'bar']
            );

            $this->fcm->setReceivers($receivers);
            $this->fcm->setMessage($message);
            $this->fcm->sendMessage();
        }
    }
}

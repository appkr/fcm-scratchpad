<?php

namespace App\Services;

use App\Device;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Response\DownstreamResponse;

class FCMHandler
{
    /**
     * 푸쉬 메시지를 보낼 단말기의 push_service_id(push_service_id) 목록.
     *
     * @var array[string $push_service_id]
     */
    protected $to = [];

    /**
     * 보낼 메시지.
     *
     * @var array[string $key => string $value]
     */
    protected $data = [];

    /**
     * @var \LaravelFCM\Sender\FCMSender
     */
    protected $fcm;

    /**
     * 전송이 실패해서 여러 번 재전송할 때를 대비해 한 번 만든 메시지 인스턴스를 캐시하는 저장소.
     *
     * @var array
     *  [
     *      'optionBuilder' => \LaravelFCM\Message\Options,
     *      'notificationBuilder' => \LaravelFCM\Message\PayloadNotification,
     *      'data' => \LaravelFCM\Message\PayloadData
     *  ]
     */
    private $cache = [];

    /**
     * FCMHandler constructor.
     * @param array $to[string $push_service_id]
     * @param array $data[string $key => string $value]
     */
    public function __construct(array $to = [], $data = [])
    {
        $this->to = $to;
        $this->data = $data;
        $this->fcm = app('fcm.sender');
    }

    /**
     * 푸쉬 메시지를 보낼 단말기의 registration_id 목록을 설정합니다.
     *
     * @param array $to[string $push_service_id]
     * @return $this
     */
    public function to(array $to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * 푸쉬 메시지 전송을 라이브러리에 위임하고, 전송 결과를 처리합니다.
     *
     * @param array $data[string $key => string $value]
     * @return DownstreamResponse
     * @throws \Exception
     */
    public function send($data = [])
    {
        $this->data = $data;
        $retryCount = 0;
        $retryInterval = [1, 2, 4];

        $response = $this->fire();

        if ($response->numberModification() > 0) {
            // 단말기 공장 초기화 등의 이유로 registration_id(push_service_id)가 바뀌었습니다.
            $tokens = $response->tokensModify();
            $this->updateDevices($tokens);
        }

        if ($response->numberFailure() > 0) {

            if ($tokens = $response->tokensToDelete()) {
                // 해당 registration_id를 가진 단말기가 구글 FCM 서비스에 등록되어 있지 않습니다.
                $this->deleteDevices($tokens);
            }

            if ($tokens = $response->tokensToRetry()) {
                $this->to($tokens);

                if (isset($retryInterval[$retryCount])) {
                    // 메시지 전송에 실패했습니다.
                    // 1,2,4초 간격으로 총 세 번 재 시도합니다.
                    sleep($retryInterval[$retryCount]);
                    $response = $this->send();
                    $retryCount += 1;
                }

                if ($response->numberFailure()) {
                    // 세 번을 재시도했음에도 성공하지 못했습니다.
                    throw new \Exception(
                        '푸쉬 메시지를 보낼 수 없습니다.: ' .
                        implode(PHP_EOL, $response->tokensWithError())
                    );
                }
            }
        }

        return $response;
    }

    /**
     * 푸쉬 메시지를 전송합니다.
     *
     * @return DownstreamResponse
     */
    protected function fire()
    {
        return $this->fcm->sendTo(
            $this->getTo(),
            $this->buildOption(),
            null,
            $this->buildPayload()
        );
    }

    /**
     * 중복 수신자를 제거한 수신자 목록을 반환합니다.
     *
     * @return array[string $push_service_id]
     */
    protected function getTo()
    {
        return array_unique($this->to);
    }

    /**
     * 푸쉬 메시지 전송 옵션을 설정합니다.
     *
     * @return \LaravelFCM\Message\Options
     */
    protected function buildOption()
    {
        if (array_key_exists('optionBuilder', $this->cache)) {
            return $this->cache['optionBuilder'];
        }

        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60*20);

        return $this->cache['optionBuilder'] = $optionBuilder->build();
    }

    /**
     * (단말기의 Notification Center에 표시될) 알림 제목과 본문을 설정합니다.
     *
     * @param string $title
     * @param string $body
     * @return \LaravelFCM\Message\PayloadNotification
     */
    protected function buildNotification(string $title, string $body)
    {
        if (array_key_exists('notificationBuilder', $this->cache)) {
            return $this->cache['notificationBuilder'];
        }

        $notificationBuilder = new PayloadNotificationBuilder($title);
        $notificationBuilder->setBody($body)->setSound('default');

        return $this->cache['notificationBuilder'] = $notificationBuilder->build();
    }

    /**
     * 메시지 본문을 설정합니다.
     *
     * @return \LaravelFCM\Message\PayloadData
     */
    protected function buildPayload()
    {
        if (array_key_exists('data', $this->cache)) {
            return $this->cache['data'];
        }

        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData($this->data);

        return $this->cache['data'] = $dataBuilder->build();
    }

    /**
     * 변경된 단말기의 토큰을 DB에 기록합니다.
     *
     * @param array[string $oldKey => string $newKey] $tokens
     * @return bool
     */
    protected function updateDevices(array $tokens)
    {
        foreach ($tokens as $old => $new) {
            $device = Device::wherePushServiceId($old)->firstOrFail();
            $device->push_service_id = $new;
            $device->save();
        }

        return true;
    }

    /**
     * 유효하지 않은 단말기 토큰을 DB에서 삭제합니다.
     *
     * @param array[string $push_service_id] $tokens
     * @return bool
     */
    protected function deleteDevices(array $tokens) {
        foreach ($tokens as $token) {
            $device = Device::wherePushServiceId($token)->firstOrFail();
            $device->delete();
        }

        return true;
    }
}
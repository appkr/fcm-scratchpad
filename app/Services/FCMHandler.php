<?php

namespace App\Services;

use App\Device;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Response\DownstreamResponse;
use Log;

/**
 * Class FCMHandler
 * @package App\Services
 */
class FCMHandler
{
    /** @var array FCM을 수신한 registration_id 목록 */
    private $to = [];

    /** @var array FCM 데이터 메시지 본문 */
    private $data = [];

    /** @var string FCM 알림 제목 */
    private $title;

    /** @var string FCM 알림 본문 */
    private $body;

    /** @var array 전송 실패시 재시도 간격 */
    private $retryIntervals = [1, 2, 4];

    /** @var int 전송 실패시 재시도 카운트 */
    private $retryIndex = 0;

    /** @var \LaravelFCM\Sender\FCMSender */
    protected $fcm;

    /**
     * 전송이 실패해서 여러 번 재전송할 때를 대비해 한 번 만든 메시지 인스턴스를 캐시하는 저장소.
     *
     * @var array
     *  [
     *      'optionBuilder' => \LaravelFCM\Message\Options,
     *      'notificationBuilder' => \LaravelFCM\Message\PayloadNotification,
     *      'dataBuilder' => \LaravelFCM\Message\PayloadData
     *  ]
     */
    private $cache = [];

    /**
     * 푸쉬 메시지를 보낼 단말기의 registration_id 목록을 설정합니다.
     *
     * @param array $to
     * @return $this
     */
    public function to(array $to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * 푸쉬 메시지로 보낼 데이터 본문을 설정합니다.
     *
     * @param array $data
     * @return $this
     */
    public function data(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 푸쉬 메시지로 보낼 알림 제목과 본문을 설정합니다.
     *
     * @param string $title
     * @param string $body
     * @return $this
     */
    public function notification(string $title = null, string $body = null)
    {
        $this->title = $title;
        $this->body = $body;

        return $this;
    }

    /**
     * 메시지 전송 실패시 재시도 간격과 회수를 설정합니다.
     *
     * @param array[int] $intervals
     * @return $this
     */
    public function retryIntervals(array $intervals = [])
    {
        if (! empty($intervals)) {
            $this->retryIntervals = $intervals;
        }

        return $this;
    }

    /**
     * 푸쉬 메시지 전송을 라이브러리에 위임하고, 전송 결과를 처리합니다.
     *
     * @param int $sleep
     * @return DownstreamResponse
     * @throws Exception
     */
    public function send($sleep = 0)
    {
        sleep($sleep);

        $response = $this->fire();
        $this->log($response);

        if ($response->numberModification() > 0) {
            // 메시지는 성공적으로 전달되었습니다.
            // 단말기 공장 초기화 등의 이유로 구글 FCM Server에 등록된 registration_id가 바뀌었습니다.
            $tokens = $response->tokensModify();
            $this->updateDevices($tokens);
        }

        if ($response->numberFailure() > 0) {
            if ($tokens = $response->tokensToDelete()) {
                // 해당 registration_id를 가진 단말기가 구글 FCM 서비스에 등록되어 있지 않습니다.
                $this->deleteDevices($tokens);
            }

            if ($tokens = $response->tokensToRetry()) {
                // 구글 FCM Server가 5xx 응답을 반환했습니다.
                $this->to($tokens);

                if (isset($this->retryIntervals[$this->retryIndex])) {
                    // 메시지 전송에 실패했습니다.
                    // static::$retryIntervals에 설정된 간격으로 재시도합니다.
                    $this->send(
                        $this->retryIntervals[$this->retryIndex++]
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
        /** @var FCMSender $fcmSender */
        $fcmSender = app('fcm.sender');
        $notification = ($this->title && $this->body)
            ? $this->buildNotification() : null;

        return $fcmSender->sendTo(
            $this->getTo(),
            $this->buildOption(),
            $notification,
            $this->buildPayload()
        );
    }

    /**
     * 중복 수신자를 제거한 수신자 목록을 반환합니다.
     *
     * @return array
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
//        $optionBuilder->setCollapseKey('collapse_key');
//        $optionBuilder->setDelayWhileIdle(true);
//        $optionBuilder->setTimeToLive(60*2);
//        $optionBuilder->setDryRun(false);

        return $this->cache['optionBuilder'] = $optionBuilder->build();
    }

    /**
     * (단말기의 Notification Center에 표시될) 알림 제목과 본문을 설정합니다.
     *
     * @return \LaravelFCM\Message\PayloadNotification
     */
    protected function buildNotification()
    {
        if (array_key_exists('notificationBuilder', $this->cache)) {
            return $this->cache['notificationBuilder'];
        }

        $notificationBuilder = new PayloadNotificationBuilder();
        $notificationBuilder->setTitle()->setBody()->setSound('default');

        return $this->cache['notificationBuilder'] = $notificationBuilder->build();
    }

    /**
     * 메시지 본문을 설정합니다.
     *
     * @return \LaravelFCM\Message\PayloadData
     */
    protected function buildPayload()
    {
        if (array_key_exists('dataBuilder', $this->cache)) {
            return $this->cache['dataBuilder'];
        }

        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData($this->data);

        return $this->cache['dataBuilder'] = $dataBuilder->build();
    }

    /**
     * 변경된 단말기의 토큰을 DB에 기록합니다.
     *
     * @param array[$oldKey => $newKey] $tokens
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
     * @param array[$token] $tokens
     * @return bool
     */
    protected function deleteDevices(array $tokens) {
        foreach ($tokens as $token) {
            $device = Device::wherePushServiceId($token)->firstOrFail();
            $device->delete();
        }

        return true;
    }

    /**
     * 로그를 남깁니다.
     *
     * @param DownstreamResponse $response
     */
    protected function log(DownstreamResponse $response)
    {
        $logMessage = sprintf(
            "FCM broadcast (%dth try) send to %d devices success %d, fail %d, number of modified token %d.",
            $this->retryIndex,
            count($this->getTo()),
            $response->numberSuccess(),
            $response->numberFailure(),
            $response->numberModification()
        );

        $rawRequest = json_encode([
            'to' => $this->getTo(),
            'notification' => [
                'title' => $this->title,
                'body' => $this->body,
            ],
            'data' => $this->data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $rawResponse = var_export($response, true);

        Log::info($logMessage . PHP_EOL . $rawRequest . PHP_EOL . $rawResponse);
    }
}
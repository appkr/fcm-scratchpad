<?php

namespace App\Services;

use App\Device;
use DB;
use Exception;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Response\DownstreamResponse;
use LaravelFCM\Sender\FCMSender;
use Log;
use Psr\Log\LoggerInterface;

class FCMHandler
{
    const DEFAULT_TTL_IN_SECOND = 1800; // 30분

    private $fcmSender;
    private $logger;
    private $receivers = [];
    private $message = [];
    private $retryInterval = 100; // milli seconds
    private $maxTries = 3;
    private $tries = 0;

    public function __construct(FCMSender $fcmSender, LoggerInterface $logger)
    {
        $this->fcmSender = $fcmSender;
        $this->logger = $logger;
    }

    public function setReceivers(array $receivers)
    {
        $this->receivers = array_unique($receivers);
    }

    public function setMessage(array $message)
    {
        $this->message = $message;
    }

    public function sendMessage($sleep = 0)
    {
        if ($sleep > 0) {
            usleep($sleep);
        }

        $this->isReadyToSend();

        $response = null;
        try {
            $response = $this->fcmSender->sendTo(
                $this->receivers,
                $this->getDeliveryOption(),
                null,
                $this->getPayloadData()
            );

            // Note. Http Exception이 아니면, Fcm Server는 에러 메시지를 담은 응답을 반환합니다.
            $this->logRequestAndResponse($response);
            $this->updatePushServiceIdsIfAny($response);
            $this->handleDeliveryFailureIfAny($response);
        } catch (RequestException $e) {
            $this->logger->error('FCM 메시지를 전송하지 못했습니다.', [
                'error' => $e->getResponse() ? $e->getResponse()->getBody() : $e->getMessage(),
            ]);
            throw $e;
        }

        return $response;
    }

    private function isReadyToSend()
    {
        if (empty($this->receivers)) {
            throw new Exception('수신자를 제출하지 않았습니다.');
        }
        if (empty($this->message)) {
            throw new Exception('메시지를 제출하지 않았습니다.');
        }
    }

    private function getDeliveryOption()
    {
        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(self::DEFAULT_TTL_IN_SECOND);
        return $optionBuilder->build();
    }

    private function getPayloadData()
    {
        $payloadDataBuilder = new PayloadDataBuilder();
        $payloadDataBuilder->addData($this->message);
        return $payloadDataBuilder->build();
    }

    private function logRequestAndResponse(DownstreamResponse $response)
    {
        $tries = ($this->tries !== 0) ?: 1;
        $logMessage = sprintf("FCM 메시지 전송 %d번째 시도, 성공 %d건, 실패 %d건.",
            $tries, count($this->receivers), $response->numberSuccess(), $response->numberFailure());

        $this->logger->debug($logMessage, [
            'request' => [
                'to' => $this->receivers,
                'data' => $this->message,
            ],
            'response' => [
                'hasMissingPushServiceId' => $response->hasMissingToken(),
                'pushServiceIdsToModify' => $response->tokensToModify(),
                'pushServiceIdsWithError' => $response->tokensWithError(),
                'pushServiceIdsToDelete' => $response->tokensToDelete(),
                'pushServiceIdsToRetry' => $response->tokensToRetry(),
            ],
        ]);
    }

    /*
     * 변경된 단말기의 토큰을 DB에 기록합니다.
     */
    private function updatePushServiceIdsIfAny(DownstreamResponse $response)
    {
        if ($response->numberModification() <= 0) {
            return;
        }

        // [ old_push_service_id => new_push_service_id ]
        $pushServiceIdsToModify = $response->tokensToModify();

        // 메시지는 성공적으로 전달되었습니다.
        // 단말기 공장 초기화 등의 이유로 구글 FCM Server에 등록된 registration_id가 바뀌었습니다.
        $this->logger->info('FCM 메시지 전송 중: push_service_id를 Google과 동기화하고 있습니다.', [
            'push_service_id_to_modify' => $pushServiceIdsToModify
        ]);

        foreach ($pushServiceIdsToModify as $oldPushServiceId => $newPushServiceId) {
            /** @var Device $device */
            $device = Device::where('push_service_id', $oldPushServiceId)->first();
            if (null === $device) {
                continue;
            }

            try {
                DB::transaction(function () use ($device, $newPushServiceId) {
                    $device->push_service_id = $newPushServiceId;
                    $device->save();
                });
            } catch (Exception $e) {
                $this->logger->error('FCM 메시지 전송 중: push_service_id를 동기화하지 못했습니다.', [
                    'device_id' => $device->id,
                    'old_push_service_id' => $oldPushServiceId,
                    'new_push_service_id' => $newPushServiceId,
                ]);
            }
        }
    }

    /*
     * Fcm 전송 실패를 처리합니다.
     */
    private function handleDeliveryFailureIfAny(DownstreamResponse $response)
    {
        if ($response->numberFailure() <= 0) {
            return;
        }

        $pushServiceIdsToDelete = $response->tokensToDelete();
        if (! empty($pushServiceIdsToDelete)) {
            // 해당 registration_id를 가진 단말기가 구글 FCM 서비스에 등록되어 있지 않습니다.
            $this->logger->info('FCM 메시지 전송 중: 유효하지 않은 push_service_id를 가진 레코드를 삭제합니다.', [
                'push_service_ids_to_delete' => $pushServiceIdsToDelete,
            ]);

            foreach ($pushServiceIdsToDelete as $pushServiceIdToDelete) {
                /** @var Device $device */
                $device = Device::where('push_service_id', $pushServiceIdToDelete);
                if (null === $device) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($device) {
                        $device->delete();
                    });
                } catch (Exception $e) {
                    $this->logger->error('FCM 메시지 전송 중: 유효하지 않은 push_service_id를 가진 레코드를 삭제하지 못했습니다.', [
                        'device_id' => $device->id,
                        'push_service_id_to_delete' => $pushServiceIdToDelete
                    ]);
                }
            }
        }

        $pushServiceIdsToRetry = $response->tokensToRetry();
        if (! empty($pushServiceIdsToRetry)) {
            $this->receivers = $pushServiceIdsToRetry;
            if ($this->tries >= $this->maxTries) {
                // 재시도 했지만 메시지 전송에 실패했습니다.
                throw new Exception('FCM 메시지를 전송하지 못했습니다.');
            }

            $this->logger->info("FCM 메시지 전송 중: 다음 push_service_id에 대해 재 전송 시도합니다.", [
                'push_service_ids_to_retry' => $pushServiceIdsToRetry,
            ]);

            // 최대 3회, 1회는 기본값, 다음 루프는 2회, 3회까지 실행됨.
            $this->tries = $this->actualRetryCount + 1;
            // (최초 1회 200밀리초 뒤 실행, 2회 400밀리초 뒤, 3회 800밀리초 뒤) -> 프로세스가 총 1.4초동안 실행됨.
            // @see https://firebase.google.com/docs/cloud-messaging/http-server-ref?hl=ko#error-codes
            $this->retryInterval = $this->retryInterval * 2;
            $this->sendMessage($this->retryInterval);
        }
    }
}

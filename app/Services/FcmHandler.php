<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;

class FcmHandler
{
    const MAX_TOKEN_PER_REQUEST = 1000;
    const API_ENDPOINT = 'fcm/send';

    private $httpClient;
    private $deviceRepo;
    private $logger;

    private $receivers;
    private $message;
    private $retryIntervalInUs = 100000; // 100ms
    private $maxRetryCount = 3;
    private $retriedCount = 0;

    public function __construct(
        GuzzleClient $httpClient,
        FcmDeviceRepository $deviceRepo,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->deviceRepo = $deviceRepo;
        $this->logger = $logger;
    }

    public function sendMessage()
    {
        if (count(array_filter($this->receivers)) === 0) {
            $this->logProgress('푸쉬 알림을 전송을 건너 뜁니다: 수신자가 없습니다.');
            return;
        }

        if ($this->isInitialRequest()) {
            $this->logProgress('푸쉬 알림을 전송합니다.', [
                'receivers' => $this->receivers,
                'message' => $this->message,
            ], 'debug');
        }

        try {
            if (count($this->receivers) > self::MAX_TOKEN_PER_REQUEST) {
                $response = null;
                foreach (array_chunk($this->receivers, self::MAX_TOKEN_PER_REQUEST) as $chunk) {
                    $responsePartial = $this->_sendMessage($chunk);
                    if (!$response) {
                        $response = $responsePartial;
                    } else {
                        $response->merge($responsePartial);
                    }

                    usleep(1);
                }
            } else {
                $response = $this->_sendMessage($this->receivers);
            }

            $this->updatePushServiceIdsIfAny($response);
            $this->handleDeliveryFailureIfAny($response);

            $this->logProgress("푸쉬 알림을 전송했습니다.", [
                'receiver' =>$this->receivers,
            ]);
        } catch (Exception $e) {
            $this->logProgress("푸쉬 알림을 전송하지 못헸습니다: {$e->getMessage()}", [
                'receiver' => $this->receivers,
            ], 'error');
            throw $e;
        }
    }

    public function setReceivers(array $receivers)
    {
        $this->receivers = $receivers;
    }

    public function setMessage(array $message)
    {
        $this->message = $message;
    }

    private function _sendMessage(array $tokens)
    {
        $request = $this->getRequest($tokens);
        $guzzleResponse = $this->httpClient->send($request);

        return new DownstreamResponse($guzzleResponse, $tokens);
    }

    private function getRequest(array $tokens)
    {
        // 'to' for single receiver,
        // 'registration_ids' for multiple receivers
        $httpBody = \GuzzleHttp\json_encode([
            'registration_ids' => $tokens,
            'notification' => null,
            'data' => $this->message,
        ]);

        return new Request('POST', self::API_ENDPOINT, [], $httpBody);
    }

    private function updatePushServiceIdsIfAny(DownstreamResponse $response)
    {
        if ($response->numberModification() <= 0) {
            return;
        }

        /**
         * @var array $pushServiceIdsToModify {
         *     @var string $oldPushServiceId => string $newPushServiceId
         * }
         */
        $pushServiceIdsToModify = $response->tokensToModify();

        // 메시지는 성공적으로 전달되었습니다.
        // 단말기 공장 초기화 등의 이유로 구글 FCM Server에 등록된 registration_id가 바뀌었습니다.
        $this->logProgress('구글 서버와 push_service_id 를 동기화합니다.', [
            'push_service_id_to_modify' => $pushServiceIdsToModify
        ]);

        foreach ($pushServiceIdsToModify as $oldPushServiceId => $newPushServiceId) {
            $this->deviceRepo->updateFcmDevice($oldPushServiceId, $newPushServiceId);
        }
    }

    private function handleDeliveryFailureIfAny(DownstreamResponse $response)
    {
        if ($response->numberFailure() <= 0) {
            return;
        }

        $pushServiceIdsToDelete = $response->tokensToDelete();
        if (! empty($pushServiceIdsToDelete)) {
            // 해당 registration_id를 가진 단말기가 구글 FCM 서비스에 등록되어 있지 않습니다.
            $this->logProgress('사용불가한 push_service_id 를 삭제합니다.', [
                'push_service_ids_to_delete' => $pushServiceIdsToDelete
            ]);

            foreach ($pushServiceIdsToDelete as $pushServiceIdToDelete) {
                $this->deviceRepo->deleteFcmDevice($pushServiceIdToDelete);
            }
        }

        $pushServiceIdsToRetry = $response->tokensToRetry();
        if (! empty($pushServiceIdsToRetry)) {
            if ($this->isFinalRetry()) {
                // 재시도 했지만 메시지 전송에 실패했습니다.
                throw new Exception();
            }

            // 최대 3회, 1회는 기본값, 다음 루프는 2회, 3회까지 실행됨.
            $this->retriedCount = $this->retriedCount + 1;
            // (최초 1회 200밀리초 뒤 실행, 2회 400밀리초 뒤, 3회 800밀리초 뒤) -> 프로세스가 총 1.4초동안 실행됨.
            // @see https://firebase.google.com/docs/cloud-messaging/http-server-ref?hl=ko#error-codes
            $this->retryIntervalInUs = $this->retryIntervalInUs * 2;

            usleep($this->retryIntervalInUs);

            $this->logProgress("{$this->getOrdinalRetryCount()} 재전송 시도합니다.", [
                'retried_count' => $this->retriedCount,
                'push_service_ids_to_retry' => $pushServiceIdsToRetry,
            ]);

            $this->receivers = $pushServiceIdsToRetry;
            $this->sendMessage();
        }
    }

    private function isInitialRequest()
    {
        return $this->retriedCount === 0;
    }

    private function isFinalRetry()
    {
        return $this->retriedCount === $this->maxRetryCount;
    }

    private function getOrdinalRetryCount()
    {
        switch ($this->retriedCount) {
            case 1:  return '첫번째';
            case 2:  return '두번째';
            case 3:  return '세번째';
            default: return '';
        }
    }

    private function logProgress(string $message, $context = [], string $level = 'debug')
    {
        $this->logger->log($level, "[FcmHandler] {$message}", $context);
    }
}

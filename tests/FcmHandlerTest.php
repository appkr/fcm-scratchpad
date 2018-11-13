<?php

use App\Device;
use App\Services\FcmDeviceRepository;
use App\Services\FcmHandler;
use App\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class FcmHandlerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;
    use InteractsWithDatabase;

    const FAKE_PUSH_SERVICE_ID = '123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234';
    const FAKE_MESSAGE = ['i_am' => 'fcm'];

    /** @var User */
    private $user;

    public function setUp()
    {
        parent::setUp();
        $this->seedUserAndDevice();
    }

    /* TESTS */

    public function test_can_send_fcm_message()
    {
        $fcmResBody = '{
            "multicast_id": 108,
            "success": 1,
            "failure": 0,
            "canonical_ids": 0,
            "results": [
                { "message_id": "1:08" }
            ]
        }';

        $fcm = $this->getMockFcmHandler(Response::HTTP_OK, $fcmResBody);
        $fcm->setReceivers($this->user->getPushServiceIds());
        $fcm->setMessage(self::FAKE_MESSAGE);
        $fcm->sendMessage();
    }

    public function test_can_send_more_then_one_thousand_messages_at_once()
    {
        $fcmResBody = '{
            "multicast_id": 108,
            "success": 1,
            "failure": 0,
            "canonical_ids": 0,
            "results": [
                { "message_id": "1:08" }
            ]
        }';

        $channels = [];
        foreach (range(1, 1010) as $index) {
            $channels[] = Str::random(174);
        }

        $fcm = $this->getMockFcmHandler(Response::HTTP_OK, $fcmResBody);
        $fcm->setReceivers($channels);
        $fcm->setMessage(self::FAKE_MESSAGE);
        $fcm->sendMessage();
    }

    public function test_push_service_id_can_be_changed()
    {
        $newId = Str::random(174);
        $fcmResBody = '{
            "multicast_id": 108,
            "success": 0,
            "failure": 0,
            "canonical_ids": 1,
            "results": [
                { "message_id": "1:2342", "registration_id": "'.$newId.'" }
            ]
        }';

        $fcm = $this->getMockFcmHandler(Response::HTTP_OK, $fcmResBody);
        $fcm->setReceivers($this->user->getPushServiceIds());
        $fcm->setMessage(self::FAKE_MESSAGE);
        $fcm->sendMessage();

        $this->dontSeeInDatabase('devices', [
            'push_service_id' => self::FAKE_PUSH_SERVICE_ID,
        ]);
        $this->seeInDatabase('devices', [
            'push_service_id' => $newId,
        ]);
    }

    public function test_deprecated_push_service_id_must_be_removed()
    {
        $fcmResBody = '{
            "multicast_id": 216,
            "success": 0,
            "failure": 1,
            "canonical_ids": 0,
            "results": [
                { "error": "NotRegistered" }
            ]
        }'; // another response type: { "error": "InvalidRegistration" }

        $fcm = $this->getMockFcmHandler(Response::HTTP_OK, $fcmResBody);
        $fcm->setReceivers($this->user->getPushServiceIds());
        $fcm->setMessage(self::FAKE_MESSAGE);
        $fcm->sendMessage();

        $this->dontSeeInDatabase('devices', [
            'push_service_id' => self::FAKE_PUSH_SERVICE_ID,
        ]);
    }

    public function test_resending_fcm_message_three_times_again_when_it_fails()
    {
        $fcmResBody = '{
            "multicast_id": 216,
            "success": 0,
            "failure": 1,
            "canonical_ids": 0,
            "results": [
                { "error": "Unavailable" }
            ]
        }';

        $this->expectException(Exception::class);

        $fcm = $this->getMockFcmHandler(Response::HTTP_OK, $fcmResBody);
        $fcm->setReceivers($this->user->getPushServiceIds());
        $fcm->setMessage(self::FAKE_MESSAGE);
        $fcm->sendMessage();
    }

    /* HELPERS */

    private function seedUserAndDevice()
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        factory(Device::class)->create([
            'push_service_id' => self::FAKE_PUSH_SERVICE_ID,
            'user_id' => $user->getKey(),
        ]);

        $this->user = $user->fresh(['devices']);
    }

    private function getMockFcmHandler(int $statusCode = 200, string $fcmResBody = '')
    {
        $response =  new GuzzleResponse($statusCode, [], $fcmResBody);
        $httpClient = Mockery::mock(GuzzleClient::class);
        $httpClient->shouldReceive('send')->andReturn($response);
        $deviceRepo = $this->app->make(FcmDeviceRepository::class);
        $logger = $this->app->make(LoggerInterface::class);

        return new FcmHandler($httpClient, $deviceRepo, $logger);
    }
}

<?php

use Illuminate\Http\Request;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth.basic.once');

Route::post('/devices', 'DevicesController@upsert');

Route::put('foo', 'FooController@bar');

Route::get('fcm', function () {
    $optionBuiler = new OptionsBuilder();
    $optionBuiler->setTimeToLive(60*20);

    $notificationBuilder = new PayloadNotificationBuilder('알림 제목');
    $notificationBuilder->setBody('알림 본문')->setSound('default');

    $dataBuilder = new PayloadDataBuilder();
    $dataBuilder->addData(['foo' => 'bar']);

    $option = $optionBuiler->build();
    $notification = $notificationBuilder->build();
    $data = $dataBuilder->build();

    $token = 'eIrjxWASTb0:APA91bF8mv9AdXMAxQ0ALcvFJ4zvfzLxDs7LmGXrKB4btklQKuhcD94KTJV7tCghnxSQMAsShTjzjWHfWDC1aXe_JAQO0Ao4nuFEfpQI0QaUyX7Mh0aFm1RLVDhcP7nAArzaxF6jBFJx';

    $downstreamResponse = app('fcm.sender')->sendTo($token, $option, $notification, $data);

    var_dump('raw', $downstreamResponse);
    var_dump('get_class_methods', get_class_methods($downstreamResponse));
    var_dump('numberSuccess', $downstreamResponse->numberSuccess());
    var_dump('numberFailure', $downstreamResponse->numberFailure());
    var_dump('numberModification', $downstreamResponse->numberModification());
    var_dump('tokensToDelete', $downstreamResponse->tokensToDelete());
    var_dump('tokensToModify', $downstreamResponse->tokensToModify());
    var_dump('tokensToRetry', $downstreamResponse->tokensToRetry());
});

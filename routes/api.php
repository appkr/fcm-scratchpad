<?php

use Illuminate\Http\Request;
use App\Services\FCMHandler;

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

Route::get('fcm', function (Request $request, FCMHandler $fcm) {
    $user = $request->user();
    $to = $user->devices()->pluck('push_service_id')->toArray();

    if (! empty($to)) {
        $message = array_merge(
            $user->toArray(),
            ['foo' => 'bar']
        );

        $fcm->to($to)->send($message);
    }

    return response()->json([
        'success' => 'HTTP 요청 처리 완료'
    ]);
})->middleware('auth.basic.once');

Route::put('foo', 'FooController@bar');


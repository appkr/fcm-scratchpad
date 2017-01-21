<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('home', 'HomeController@index');

Route::get('users', [
    'as' => 'users.index',
    'uses' => 'UsersController@index'
]);

Route::get('users/{user}/fcm', [
    'as' => 'fcm.create',
    'uses' => 'FcmController@create'
]);

Route::post('fcm', [
    'as' => 'fcm.send',
    'uses' => 'FcmController@send'
]);
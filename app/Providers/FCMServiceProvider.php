<?php

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LaravelFCM\Sender\FCMSender;

class FCMServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(FCMSender::class, function (Application $app) {
            return $app->make('fcm.sender');
        });
    }
}

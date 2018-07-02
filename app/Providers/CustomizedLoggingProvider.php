<?php

namespace App\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class CustomizedLoggingProvider extends ServiceProvider
{
    public function boot()
    {
        $formatter = new JsonFormatter();
        $streamHandler = new StreamHandler(
            $this->app->storagePath().'/logs/laravel.log',
            $this->app->make(Repository::class)->get('app.log_level', Logger::DEBUG)
        );
        $streamHandler->setFormatter($formatter);
        $logger = $this->app->make(LoggerInterface::class);
        /** @var Logger $monolog */
        $monolog = $logger->getMonolog();
        $monolog->setHandlers([$streamHandler]);
    }
}

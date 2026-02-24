<?php

namespace AIHealth\Laravel;

use Throwable;
use Illuminate\Contracts\Debug\ExceptionHandler as LaravelHandler;

class ErrorHandler
{
    public function __construct(protected Client $client)
    {
    }

    public function register()
    {
        app(LaravelHandler::class)->reportable(function (Throwable $e) {
            $this->client->captureException($e);
        });
    }
}

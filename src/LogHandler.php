<?php

namespace AIHealth\Laravel;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Throwable;

class LogHandler
{
    public function __construct(protected Client $client)
    {
    }

    public function register()
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $levels = config('aihealth.log_levels', ['error', 'warning', 'critical', 'alert', 'emergency']);

            if (in_array($event->level, $levels)) {
                $this->client->captureLog(
                    $event->level,
                    $event->message,
                    $event->context
                );
            }
        });
    }
}

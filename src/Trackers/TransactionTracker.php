<?php

namespace AIHealth\Laravel\Trackers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use AIHealth\Laravel\Client;

class TransactionTracker
{
    public function __construct(protected Client $client, protected Application $app)
    {
    }

    public function register()
    {
        $this->app['events']->listen(RequestHandled::class, [$this, 'handle']);
    }

    public function handle(RequestHandled $event)
    {
        $request = $event->request;
        $response = $event->response;

        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');
        $durationMs = $startTime ? (int) round((microtime(true) - $startTime) * 1000) : 0;

        $routeName = $request->route() ? ($request->route()->getName() ?? $request->route()->uri()) : $request->path();

        $memoryUsage = (int) round(memory_get_peak_usage(true) / 1024 / 1024);

        $this->client->captureTransaction([
            'route_name' => $routeName,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'duration_ms' => $durationMs,
            'memory_usage_mb' => $memoryUsage,
            'status_code' => $response->getStatusCode(),
        ]);
    }
}

<?php

namespace AIHealth\Laravel;

use Throwable;
use Illuminate\Http\Request;
use AIHealth\Laravel\Transport\HttpTransport;

class Client
{
    protected HttpTransport $transport;
    protected $app;

    public function __construct(array $config, $app)
    {
        $this->app = $app;
        $this->transport = new HttpTransport($config['dsn'], $config['project_id'] ?? null);
    }

    public function captureException(Throwable $e)
    {
        if ($this->shouldIgnore()) {
            return;
        }

        $payload = [
            'type' => 'exception',
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => now()->toISOString(),
        ];

        $this->enrichAndSend($payload);
    }

    public function captureLog(string $level, string $message, array $context = [])
    {
        if ($this->shouldIgnore()) {
            return;
        }

        // Don't send context if it has an exception object inside 
        // (to avoid recursion/duplicates, as exceptions are caught separately by ErrorHandler)
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            return;
        }

        $payload = [
            'type' => 'log',
            'level' => $level,
            'message' => (string) $message,
            'context' => collect($context)->except(['exception'])->toArray(),
            'timestamp' => now()->toISOString(),
        ];

        $this->enrichAndSend($payload);
    }

    public function captureTransaction(array $data)
    {
        if ($this->shouldIgnore()) {
            return;
        }

        $payload = array_merge([
            'type' => 'transaction',
            'timestamp' => now()->toISOString(),
        ], $data);

        $this->enrichAndSend($payload);
    }

    public function captureHealth(array $data = [])
    {
        if ($this->shouldIgnore()) {
            return;
        }

        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        $dbConnected = false;
        try {
            if ($this->app->bound('db')) {
                $this->app->make('db')->connection()->getPdo();
                $dbConnected = true;
            }
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        $payload = array_merge([
            'type' => 'health',
            'timestamp' => now()->toISOString(),
            'memory_usage_mb' => round($memoryUsage, 2),
            'cpu_load' => $cpuLoad[0] ?? 0,
            'db_connected' => $dbConnected,
        ], $data);

        $this->enrichAndSend($payload);

        // Force flush if it's called individually (e.g. from an artisan command)
        if ($this->app->runningInConsole()) {
            $this->transport->flush();
        }
    }

    protected function enrichAndSend(array $payload)
    {
        try {
            /** @var Request $request */
            if ($this->app->bound('request')) {
                $request = $this->app->make('request');
                $payload['url'] = $request->fullUrl();
                $payload['method'] = $request->method();
            }
        } catch (Throwable $e) {
            // Ignore if request isn't resolvable
        }

        $payload['env'] = $this->app->environment();

        $this->transport->send($payload);
    }

    protected function shouldIgnore(): bool
    {
        try {
            if ($this->app->bound('request')) {
                $request = $this->app->make('request');
                // Never capture errors originating from the ingest ingest endpoint itself
                // to prevent infinite loops (reporting an error about reporting an error)
                if ($request->is('api/ingest*')) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            // Ignore
        }

        return false;
    }
}

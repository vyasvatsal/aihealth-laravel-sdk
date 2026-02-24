<?php

namespace AIHealth\Laravel\Transport;

use Illuminate\Support\Facades\Http;

class HttpTransport
{
    protected array $pendingPayloads = [];
    protected string $endpoint;
    protected string $projectKey;

    public function __construct(protected string $dsn)
    {
        // Parse DSN format: http://project-key@127.0.0.1:8000/api/ingest
        $parsed = parse_url($dsn);

        $this->projectKey = $parsed['user'] ?? '';

        // Rebuild the URL without the user/pass
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/api/ingest';

        $this->endpoint = "{$scheme}://{$host}{$port}{$path}";

        // Hook into the end of the Laravel request lifecycle
        app()->terminating(function () {
            $this->flush();
        });
    }

    public function send(array $payload)
    {
        $this->pendingPayloads[] = $payload;
    }

    protected function flush()
    {
        if (empty($this->pendingPayloads)) {
            return;
        }

        try {
            // Send in batch to reduce network requests
            Http::timeout(3)
                ->withHeaders([
                    'X-Monitor-Key' => $this->projectKey,
                    'Accept' => 'application/json',
                ])
                ->post($this->endpoint, [
                    'events' => $this->pendingPayloads
                ]);
        } catch (\Exception $e) {
            // Silent fail! We NEVER crash the user's app if our tracking API is down.
            error_log('AIHealth SDK Failed to send payload: ' . $e->getMessage());
        }

        $this->pendingPayloads = [];
    }
}

<?php

namespace AIHealth\Laravel\Transport;

use Illuminate\Support\Facades\Http;

class HttpTransport
{
    protected array $pendingPayloads = [];
    protected string $endpoint;
    protected string $projectKey;
    protected ?string $projectId;
    protected ?string $appName = null;

    public function __construct(array $config)
    {
        $this->projectId = $config['project_id'] ?? null;

        // Modern environment setup using split keys 
        if (!empty($config['api_key']) && !empty($config['endpoint'])) {
            $this->projectKey = $config['api_key'];
            // Ingest endpoint should ensure /api/ingest structure based on backwards compat
            $this->endpoint = rtrim($config['endpoint'], '/');
            if (!str_ends_with($this->endpoint, '/api/ingest')) {
                // Not standard ingest path, might be a bug from the user, but we'll trust it 
                // Or let's assume aihealth.endpoint is the base URL if it doesn't end in /api/ingest
                if (!str_contains($this->endpoint, '/api')) {
                    $this->endpoint .= '/api/ingest';
                }
            }
        }
        // Legacy fallback to DSN string
        elseif (!empty($config['dsn'])) {
            $parsed = parse_url($config['dsn']);
            $this->projectKey = $parsed['user'] ?? '';
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? '';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $path = $parsed['path'] ?? '/api/ingest';

            // Auto-detect project ID from path if it matches /123
            if (empty($this->projectId) && preg_match('#^/(\d+)$#', $path, $matches)) {
                $this->projectId = $matches[1];
                $path = '/api/ingest';
            }

            $this->endpoint = "{$scheme}://{$host}{$port}{$path}";
        } else {
            // Unconfigured fallback
            $this->projectKey = '';
            $this->endpoint = '';
        }

        // Hook into the end of the Laravel request lifecycle
        app()->terminating(function () {
            $this->flush();
        });
    }

    public function setAppName(string $name)
    {
        $this->appName = $name;
    }

    public function send(array $payload)
    {
        $this->pendingPayloads[] = $payload;
    }

    public function flush()
    {
        if (empty($this->pendingPayloads)) {
            return;
        }

        try {
            // Send in batch to reduce network requests
            $headers = array_filter([
                'X-Monitor-Key' => $this->projectKey,
                'X-Project-Id' => $this->projectId,
                'Accept' => 'application/json',
            ]);

            $request = Http::timeout(15)->withHeaders($headers);

            // Safety check: Skip SSL verification if explicitly disabled or if we're on a local environment
            if (config('aihealth.verify_ssl') === false || app()->environment('local')) {
                $request->withoutVerifying();
            }

            $response = $request->post($this->endpoint, array_filter([
                'app_name' => $this->appName,
                'events' => $this->pendingPayloads
            ]));

            if ($response->failed()) {
                error_log('AIHealth SDK Ingest Failed: ' . $response->status() . ' Body: ' . $response->body());
            } else {
                error_log('AIHealth SDK Ingest Success: ' . $response->status());
            }

        } catch (\Exception $e) {
            // Silent fail! We NEVER crash the user's app if our tracking API is down.
            error_log('AIHealth SDK Failed to send payload: ' . $e->getMessage());
        }

        $this->pendingPayloads = [];
    }
}

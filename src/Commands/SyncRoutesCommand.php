<?php

namespace AIHealth\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

class SyncRoutesCommand extends Command
{
    protected $signature = 'aihealth:sync-routes';
    protected $description = 'Sync all valid GET browser routes to the AI Health Monitor for analysis.';

    public function handle()
    {
        $this->info('Discovering valid Web routes...');

        $routes = Route::getRoutes();
        $validRoutes = [];

        foreach ($routes as $route) {
            // Only care about GET requests (pages a user can actually visit)
            if (!in_array('GET', $route->methods())) {
                continue;
            }

            $uri = $route->uri();

            // Ignore API routes, internal Telescope/Nova/Horizon routes, and closures
            if (
                str_starts_with($uri, 'api/') ||
                str_starts_with($uri, '_') ||
                str_contains($uri, 'telescope') ||
                str_contains($uri, 'horizon') ||
                str_contains($uri, 'livewire')
            ) {
                continue;
            }

            // We can't easily auto-visit routes with required parameters {id} without guessing data
            if (str_contains($uri, '{') && !str_contains($uri, '{?')) {
                // Skip for now, or could generate dummy data in v2
                continue;
            }

            $validRoutes[] = [
                'uri' => '/' . ltrim($uri, '/'),
                'name' => $route->getName(),
                'action' => $route->getActionName()
            ];
        }

        $this->info('Discovered ' . count($validRoutes) . ' valid routes. Syncing to Health Monitor...');

        $endpoint = rtrim(config('aihealth.endpoint'), '/');
        $apiKey = config('aihealth.api_key');
        $projectId = config('aihealth.project_id');

        // Fallback to DSN
        if (empty($endpoint) || empty($apiKey)) {
            $dsn = config('aihealth.dsn');
            if ($dsn) {
                $parsed = parse_url($dsn);
                $apiKey = $parsed['user'] ?? '';

                $scheme = $parsed['scheme'] ?? 'https';
                $host = $parsed['host'] ?? '';
                $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

                // Usually the project ID is in the path for DSNs (e.g. /1)
                $path = $parsed['path'] ?? '/api/ingest';
                if (empty($projectId)) {
                    if (preg_match('#^/(\d+)$#', $path, $matches)) {
                        $projectId = $matches[1];
                        $path = '/api/ingest';
                    } else {
                        // If no project ID is found in the path, assume 1 as fallback for legacy systems
                        $projectId = 1;
                    }
                }

                $endpoint = $scheme . '://' . $host . $port . $path;
            }
        }

        if (empty($apiKey) || empty($projectId) || empty($endpoint)) {
            $this->error('Missing API Key, Endpoint or Project ID in config. Cannot sync.');
            return 1;
        }

        // Clean endpoint path to route to sync
        $endpoint = str_replace('/api/ingest', '/api', $endpoint);
        $syncUrl = rtrim($endpoint, '/') . '/v1/projects/routes/sync';

        try {
            $response = Http::withHeaders([
                'X-Monitor-Key' => $apiKey,
                'X-Project-Id' => $projectId,
                'Accept' => 'application/json'
            ])
                ->timeout(10)
                ->post($syncUrl, [
                    'routes' => $validRoutes
                ]);

            if ($response->successful()) {
                $this->info('✅ Successfully synced routes to the Health Monitor.');
                return 0;
            }

            $this->error('Failed to sync. Server responded: ' . $response->status());
            $this->line($response->body());
            return 1;

        } catch (\Exception $e) {
            $this->error('Connection failed: ' . $e->getMessage());
            return 1;
        }
    }
}

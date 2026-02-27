<?php

return [
    'dsn' => env('AIHEALTH_DSN'),
    'project_id' => env('AIHEALTH_PROJECT_ID'),
    'environment' => env('APP_ENV', 'production'),
    'send_logs' => env('AIHEALTH_SEND_LOGS', true),
    'send_exceptions' => env('AIHEALTH_SEND_EXCEPTIONS', true),
    'send_transactions' => env('AIHEALTH_SEND_TRANSACTIONS', true),
    'log_levels' => ['error', 'warning', 'critical', 'alert', 'emergency'],

    // Real User Monitoring (RUM) Configuration
    'private_tracking_key' => env('AIHEALTH_PRIVATE_TRACKING_KEY'),
    'rum_endpoint' => env('AIHEALTH_RUM_ENDPOINT', 'https://your-health-monitor.com/api/v1/metrics/track'),
];

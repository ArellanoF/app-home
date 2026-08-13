<?php

return [
    'enabled' => (bool) env('AVAILABILITY_MONITOR_ENABLED', true),
    'url' => env('AVAILABILITY_MONITOR_URL', env('APP_URL')),
    'recipient' => env('AVAILABILITY_MONITOR_EMAIL', env('ADMIN_EMAIL')),
    'failure_threshold' => (int) env('AVAILABILITY_MONITOR_FAILURE_THRESHOLD', 2),
    'timeout' => (int) env('AVAILABILITY_MONITOR_TIMEOUT', 10),
];

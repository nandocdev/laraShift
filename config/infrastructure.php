<?php

return [
    'railway' => [
        'api_token' => env('INFRASTRUCTURE_RAILWAY_API_TOKEN'),
        'project_id' => env('INFRASTRUCTURE_RAILWAY_PROJECT_ID'),
        'service_id' => env('INFRASTRUCTURE_RAILWAY_SERVICE_ID'),
    ],
    'health' => [
        /**
         * List of IPs allowed to access the /central/health endpoint.
         * If empty, IP restriction is disabled (fallback to auth:central).
         */
        'allowed_ips' => array_filter(explode(',', env('INFRASTRUCTURE_HEALTH_ALLOWED_IPS', ''))),
    ],
];

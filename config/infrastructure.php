<?php

return [
    'health' => [
        /**
         * List of IPs allowed to access the /central/health endpoint.
         * If empty, IP restriction is disabled (fallback to auth:central).
         */
        'allowed_ips' => array_filter(explode(',', env('INFRASTRUCTURE_HEALTH_ALLOWED_IPS', ''))),
    ],
];

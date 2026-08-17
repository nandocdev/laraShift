<?php

declare(strict_types=1);

return [
    /*
     * Master switch. When disabled, MeteringManager::record() becomes a no-op
     * (events are neither persisted nor counted). Rollups/aggregation still work.
     */
    'enabled' => env('METERING_ENABLED', true),

    /*
     * Active metered-billing provider. 'null' disables billing reporting.
     * Supported values: 'dlocal'.
     *
     * The concrete MeterBillingProvider implementation is bound by the
     * corresponding integration module (e.g. DlocalServiceProvider) when this
     * flag matches. This keeps the Metering module fully decoupled from any
     * gateway implementation.
     */
    'provider' => env('METERING_PROVIDER'),

    /*
     * Meter registry — the source of truth for every measurable metric.
     *
     * Each meter defines:
     *   - name:               human-readable label.
     *   - unit:               unit name used in reports (e.g. 'request').
     *   - aggregation:        'sum' (counts accumulate over the period) or
     *                         'max' (high-water mark / gauge).
     *   - billable:           whether usage is reported to the billing provider.
     *   - provider_event_name: optional external event name used by the provider
     *                         (e.g. a dLocal product reference).
     *
     * Quota limits are NOT defined here: they live in the plan definition
     * (Plan features['quotas'][meter]) and are resolved via TenantContract.
     */
    'meters' => [
        'bookings' => [
            'name' => 'Bookings',
            'unit' => 'booking',
            'aggregation' => 'sum',
            'billable' => false,
        ],

        'api_requests' => [
            'name' => 'API Requests',
            'unit' => 'request',
            'aggregation' => 'sum',
            'billable' => false,
        ],

        'invitations' => [
            'name' => 'Invitations',
            'unit' => 'invitation',
            'aggregation' => 'sum',
            'billable' => false,
        ],

        'api_keys' => [
            'name' => 'API Keys',
            'unit' => 'key',
            'aggregation' => 'max',
            'billable' => false,
        ],

        'staff' => [
            'name' => 'Staff Members',
            'unit' => 'member',
            'aggregation' => 'max',
            'billable' => false,
        ],

        'branches' => [
            'name' => 'Branches',
            'unit' => 'branch',
            'aggregation' => 'max',
            'billable' => false,
        ],

        /*
         * Example of a billable meter. This is scaffolding: adjust or remove it
         * per product. When billable is true the aggregated usage is reported to
         * the configured MeterBillingProvider at period close.
         */
        'whatsapp_messages' => [
            'name' => 'WhatsApp Messages',
            'unit' => 'message',
            'aggregation' => 'sum',
            'billable' => true,
            'provider_event_name' => 'whatsapp_messages',
        ],
    ],
];

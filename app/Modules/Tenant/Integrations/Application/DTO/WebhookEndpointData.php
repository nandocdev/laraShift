<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\DTO;

use Spatie\LaravelData\Data;

class WebhookEndpointData extends Data
{
    /**
     * @param  array<int, string>  $events
     */
    public function __construct(
        public readonly string $url,
        public readonly array $events,
    ) {}
}

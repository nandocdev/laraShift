<?php

declare(strict_types=1);

use App\Modules\Central\Infrastructure\Services\HorizonQueueResolver;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves static bucket queues for horizon', function () {
    $queues = HorizonQueueResolver::resolve();

    expect($queues)->toContain('default');
    expect($queues)->toContain('notifications');
    expect($queues)->toContain('broadcasts');
    expect($queues)->toContain('webhooks-priority');
    
    // Verify at least one bucket queue exists
    expect($queues)->toContain('tenant.b1.default');
    expect($queues)->toContain('tenant.b5.low');
});

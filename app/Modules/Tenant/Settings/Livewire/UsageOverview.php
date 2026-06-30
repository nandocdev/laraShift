<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UsageOverview extends Component
{
    public array $metrics = [
        'staff' => 'Active Staff Members',
        'bookings' => 'Monthly Bookings',
        'invitations' => 'Pending Invitations',
        'api_keys' => 'Active API Keys',
    ];

    public function render(): View
    {
        $quota = app(QuotaManager::class);
        $tenant = tenant();

        $stats = [];
        foreach ($this->metrics as $key => $label) {
            $current = $quota->getCurrentUsage($tenant, $key);
            $limit = $quota->getLimit($tenant, $key);

            $stats[] = [
                'key' => $key,
                'label' => __($label),
                'current' => $current,
                'limit' => $limit,
                'percentage' => $limit > 0 ? min(100, round(($current / $limit) * 100)) : 0,
                'is_unlimited' => $limit === -1,
            ];
        }

        return view('settings-tenant::livewire.usage-overview', [
            'stats' => $stats,
        ]);
    }
}

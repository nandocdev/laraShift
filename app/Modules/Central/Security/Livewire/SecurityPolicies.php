<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Security\Models\TenantEncryptionKey;
use App\Modules\Shared\Models\Activity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class SecurityPolicies extends Component
{
    use WithPagination;

    public string $search = '';

    public function render(): View
    {
        $query = Tenant::whereNull('archived_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            });
        }

        $tenants = $query->orderBy('name')->paginate(20);

        $tenantIds = $tenants->pluck('id');
        $activeKeys = TenantEncryptionKey::whereIn('tenant_id', $tenantIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('tenant_id');

        $totalActiveKeys = TenantEncryptionKey::where('is_active', true)->count();
        $keysNearingRotation = TenantEncryptionKey::where('is_active', true)
            ->where('created_at', '<', now()->subDays(85))
            ->count();
        $activeTenants = Tenant::whereNull('archived_at')->count();
        $plans = Plan::where('is_active', true)->get();

        $planKeyCounts = [];
        foreach ($plans as $plan) {
            $planTenantIds = Tenant::where('plan_id', $plan->slug)->pluck('id');
            $planKeyCounts[$plan->slug] = TenantEncryptionKey::whereIn('tenant_id', $planTenantIds)
                ->where('is_active', true)
                ->count();
        }

        $recentEvents = Activity::whereIn('log_name', ['security'])
            ->latest()
            ->take(50)
            ->get();

        return view('security::pages.policies', [
            'tenants' => $tenants,
            'activeKeys' => $activeKeys,
            'totalActiveKeys' => $totalActiveKeys,
            'keysNearingRotation' => $keysNearingRotation,
            'activeTenants' => $activeTenants,
            'plans' => $plans,
            'planKeyCounts' => $planKeyCounts,
            'recentEvents' => $recentEvents,
        ]);
    }
}

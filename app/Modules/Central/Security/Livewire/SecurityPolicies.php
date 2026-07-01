<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Security\Models\TenantEncryptionKey;
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

        return view('security::pages.policies', [
            'tenants' => $tenants,
            'activeKeys' => $activeKeys,
        ]);
    }
}

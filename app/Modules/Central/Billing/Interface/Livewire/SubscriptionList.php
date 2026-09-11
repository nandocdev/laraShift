<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\CancelSubscriptionAction;
use App\Modules\Central\Billing\Application\Actions\SubscribeTenantAction;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class SubscriptionList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $gatewayFilter = '';

    public string $newTenantSlug = '';

    public string $newPlanSlug = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedGatewayFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'gatewayFilter']);
        $this->resetPage();
    }

    public function create(SubscribeTenantAction $action): void
    {
        $this->validate([
            'newTenantSlug' => 'required|string|exists:tenants,slug',
            'newPlanSlug' => 'required|string|exists:plans,slug',
        ]);

        $tenant = Tenant::where('slug', $this->newTenantSlug)->firstOrFail();

        try {
            $action->execute($tenant, $this->newPlanSlug);
            $this->reset(['newTenantSlug', 'newPlanSlug']);
            $this->resetPage();

            session()->flash('status', __('Subscription created successfully.'));
        } catch (\Exception $e) {
            $this->addError('newPlanSlug', $e->getMessage());
        }
    }

    public function cancel(string $id, CancelSubscriptionAction $action): void
    {
        $subscription = Subscription::find($id);

        if (! $subscription) {
            return;
        }

        $action->execute($subscription);

        session()->flash('status', __('Subscription will be canceled at period end.'));
    }

    public function reactivate(string $id, CancelSubscriptionAction $action): void
    {
        $subscription = Subscription::find($id);

        if (! $subscription) {
            return;
        }

        $action->resume($subscription);

        session()->flash('status', __('Subscription reactivated.'));
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    #[Computed]
    public function plans(): array
    {
        try {
            return Plan::where('is_active', true)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn ($plan) => ['slug' => $plan->slug, 'name' => $plan->name])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function gateways(): array
    {
        try {
            return Subscription::distinct()->orderBy('gateway')->pluck('gateway')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function render(): View
    {
        $subscriptions = Subscription::query()
            ->when($this->search !== '', function ($query) {
                $ids = Tenant::where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('slug', 'like', '%'.$this->search.'%')
                    ->pluck('id')
                    ->all();
                $query->whereIn('tenant_id', $ids);
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->gatewayFilter !== '', fn ($query) => $query->where('gateway', $this->gatewayFilter))
            ->latest()
            ->paginate(15);

        $tenants = Tenant::whereIn('id', $subscriptions->pluck('tenant_id')->unique())
            ->get()
            ->keyBy('id');

        return view('billing::livewire.subscription-list', [
            'subscriptions' => $subscriptions,
            'tenants' => $tenants,
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\CancelSubscriptionAction;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class SubscriptionDetail extends Component
{
    public Subscription $subscription;

    public string $planSlug = '';

    public function mount(Subscription $subscription): void
    {
        $this->subscription = $subscription;
        $this->planSlug = (string) ($subscription->plan_id ?? '');
    }

    public function changePlan(): void
    {
        $this->validate([
            'planSlug' => 'required|string|max:60|exists:plans,slug',
        ]);

        $plan = Plan::where('slug', $this->planSlug)->firstOrFail();

        $this->subscription->update([
            'plan_id' => $plan->id,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        $tenant = Tenant::find($this->subscription->tenant_id);

        if ($tenant) {
            $tenant->update(['plan_id' => $plan->slug]);

            activity('billing')
                ->causedBy(auth('central')->user())
                ->performedOn($tenant)
                ->withProperties(['subscription_id' => $this->subscription->id, 'plan' => $plan->slug])
                ->log('subscription_plan_changed');
        }

        $this->subscription->refresh();

        session()->flash('status', __('Subscription plan changed successfully.'));
    }

    public function cancel(CancelSubscriptionAction $action): void
    {
        $action->execute($this->subscription);
        $this->subscription->refresh();

        session()->flash('status', __('Subscription will be canceled at period end.'));
    }

    public function reactivate(CancelSubscriptionAction $action): void
    {
        if ($this->subscription->status === SubscriptionStatus::Canceled) {
            $this->subscription->update(['status' => SubscriptionStatus::Active]);
            $this->subscription->refresh();

            session()->flash('status', __('Subscription reactivated.'));

            return;
        }

        $action->resume($this->subscription);
        $this->subscription->refresh();

        session()->flash('status', __('Subscription reactivated.'));
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return Tenant::find($this->subscription->tenant_id);
    }

    #[Computed]
    public function plan(): ?Plan
    {
        try {
            return Plan::where('id', $this->subscription->plan_id)
                ->orWhere('slug', (string) $this->subscription->plan_id)
                ->first();
        } catch (\Throwable) {
            return null;
        }
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

    public function render(): View
    {
        $tenantId = $this->subscription->tenant_id;

        return view('billing::livewire.subscription-detail', [
            'payments' => $this->queryByTenant(Payment::class, $tenantId),
            'invoices' => $this->queryByTenant(Invoice::class, $tenantId),
        ]);
    }

    /**
     * @return Collection<int, mixed>
     */
    private function queryByTenant(string $model, string $tenantId): Collection
    {
        try {
            return $model::where('tenant_id', $tenantId)->latest()->limit(10)->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}

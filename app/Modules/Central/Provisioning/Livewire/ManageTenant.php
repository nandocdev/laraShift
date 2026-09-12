<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Livewire;

use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Actions\DeleteTenantAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Observability\Audit\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManageTenant extends Component
{
    use AuthorizesRequests;

    public Tenant $tenant;

    public string $name = '';

    public string $email = '';

    public string $status = '';

    public bool $maintenance_mode = false;

    public bool $read_only = false;

    public string $activeTab = 'overview';

    public string $plan_id = '';

    public string $purgeConfirmSlug = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('tenants:view');

        $this->tenant = $tenant;
        $this->name = $tenant->name;
        $this->email = $tenant->email;
        $this->status = $tenant->status;
        $this->maintenance_mode = (bool) $tenant->maintenance_mode;
        $this->read_only = (bool) $tenant->read_only;
        $this->plan_id = (string) ($tenant->plan_id ?? 'free');
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'subscription', 'usage', 'health', 'activity', 'danger'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function save(): void
    {
        $this->authorize('tenants:manage');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'status' => 'required|in:provisioning,active,pending_payment,past_due,suspended,quarantine,archived,failed,expired',
            'maintenance_mode' => 'boolean',
            'read_only' => 'boolean',
        ]);

        $this->tenant->update([
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'maintenance_mode' => $this->maintenance_mode,
            'read_only' => $this->read_only,
        ]);

        activity('provisioning')
            ->performedOn($this->tenant)
            ->log('tenant_updated');

        session()->flash('status', __('Tenant updated successfully.'));
        $this->redirect(route('central.provisioning.index'), navigate: true);
    }

    public function changePlan(): void
    {
        $this->authorize('tenants:manage');

        $this->validate([
            'plan_id' => 'required|string|max:60|exists:plans,slug',
        ]);

        $old = (string) ($this->tenant->plan_id ?? 'free');

        if ($old === $this->plan_id) {
            session()->flash('status', __('Tenant is already on this plan.'));

            return;
        }

        $this->tenant->update(['plan_id' => $this->plan_id]);

        activity('provisioning')
            ->causedBy(auth('central')->user())
            ->performedOn($this->tenant)
            ->withProperties(['from' => $old, 'to' => $this->plan_id])
            ->log('tenant_plan_changed');

        session()->flash('status', __('Plan changed successfully.'));
    }

    public function suspend(): void
    {
        $this->authorize('tenants:manage');
        $this->transitionTo('suspended', 'tenant_suspended');
    }

    public function quarantine(): void
    {
        $this->authorize('tenants:manage');

        $this->tenant->update([
            'status' => 'quarantine',
            'read_only' => true,
        ]);
        $this->status = 'quarantine';
        $this->read_only = true;

        activity('provisioning')
            ->causedBy(auth('central')->user())
            ->performedOn($this->tenant)
            ->log('tenant_quarantined_manual');

        session()->flash('status', __('Tenant quarantined.'));
    }

    public function reactivate(): void
    {
        $this->authorize('tenants:manage');

        $this->tenant->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);
        $this->status = 'active';

        activity('provisioning')
            ->causedBy(auth('central')->user())
            ->performedOn($this->tenant)
            ->log('tenant_reactivated');

        session()->flash('status', __('Tenant reactivated.'));
    }

    public function purge(DeleteTenantAction $action): void
    {
        $this->authorize('tenants:manage');

        if ($this->purgeConfirmSlug !== $this->tenant->slug) {
            $this->addError('purgeConfirmSlug', __('Slug confirmation does not match.'));

            return;
        }

        try {
            $action->execute($this->tenant, true);

            session()->flash('status', __('Tenant deletion queued successfully.'));
            $this->redirect(route('central.provisioning.index'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('purgeConfirmSlug', $e->getMessage());
        }
    }

    private function transitionTo(string $status, string $log): void
    {
        if ($this->tenant->status === $status) {
            session()->flash('status', __('Tenant is already :status.', ['status' => $status]));

            return;
        }

        $attributes = ['status' => $status];

        if ($status === 'suspended') {
            $attributes['suspended_at'] = now();
        }

        $this->tenant->update($attributes);
        $this->status = $status;

        activity('provisioning')
            ->causedBy(auth('central')->user())
            ->performedOn($this->tenant)
            ->log($log);

        session()->flash('status', __('Tenant status updated to :status.', ['status' => $status]));
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    #[Computed]
    public function plans(): array
    {
        try {
            return Plan::orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn ($plan) => ['slug' => $plan->slug, 'name' => $plan->name])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function currentSubscription(): ?array
    {
        try {
            $subscription = Subscription::where('tenant_id', $this->tenant->id)->latest()->first();

            return $subscription ? $subscription->toArray() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function usage(): array
    {
        return [
            'users' => $this->countWhere('users', 'tenant_id', $this->tenant->id),
            'subscriptions' => $this->countWhere('subscriptions', 'tenant_id', $this->tenant->id),
            'payments' => $this->countWhere('payments', 'tenant_id', $this->tenant->id),
        ];
    }

    /**
     * @return array<int, array{description: string, time: string}>
     */
    #[Computed]
    public function tenantActivity(): array
    {
        try {
            return Activity::where('subject_type', Tenant::class)
                ->where('subject_id', $this->tenant->id)
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($act) => [
                    'description' => str($act->description)->replace('_', ' ')->title()->toString(),
                    'time' => $act->created_at->diffForHumans(),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function countWhere(string $table, string $column, string $value): int
    {
        try {
            return DB::table($table)->where($column, $value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function render(): View
    {
        return view('provisioning::pages.manage-tenant');
    }
}

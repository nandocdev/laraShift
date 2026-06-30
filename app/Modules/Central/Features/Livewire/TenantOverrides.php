<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Livewire;

use App\Modules\Central\Features\Actions\ApplyTenantFeatureOverrideAction;
use App\Modules\Central\Features\Actions\ResolveTenantFeaturesAction;
use App\Modules\Central\Features\DTOs\TenantSummaryData;
use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class TenantOverrides extends Component
{
    public TenantSummaryData $tenantData;

    public string $tenantId;

    // Form state for new override
    public string $selectedFeatureKey = '';

    public string $type = 'allow';

    public string $reason = '';

    public ?string $expiresAt = null;

    public function mount(Tenant $tenant): void
    {
        $this->tenantId = $tenant->id;
        $this->tenantData = TenantSummaryData::from($tenant);
    }

    public function applyOverride(ApplyTenantFeatureOverrideAction $action): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        $this->validate([
            'selectedFeatureKey' => 'required|exists:features,key',
            'type' => 'required|in:allow,deny',
            'reason' => 'nullable|string|max:255',
            'expiresAt' => 'nullable|date|after:now',
        ]);

        try {
            $action->execute(
                $tenant,
                $this->selectedFeatureKey,
                $this->type,
                $this->reason,
                $this->expiresAt
            );

            $this->reset(['selectedFeatureKey', 'type', 'reason', 'expiresAt']);
            session()->flash('status', __('Feature override applied successfully.'));
        } catch (\Exception $e) {
            $this->addError('selectedFeatureKey', $e->getMessage());
        }
    }

    public function removeOverride(string $id, ApplyTenantFeatureOverrideAction $action): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $override = TenantFeatureOverride::where('tenant_id', $this->tenantId)->findOrFail($id);

        activity('features')
            ->performedOn($tenant)
            ->withProperties([
                'feature_key' => $override->feature?->key,
                'type' => $override->type,
                'actor' => auth('central')->id(),
                'removed_override_id' => $id,
            ])
            ->log('feature_override_removed');

        $override->delete();

        app(ResolveTenantFeaturesAction::class)->execute($tenant, true);

        session()->flash('status', __('Override removed.'));
    }

    public function render(): View
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        $overrides = TenantFeatureOverride::with('feature')
            ->where('tenant_id', $this->tenantId)
            ->get();

        $availableFeatures = Feature::where('is_active', true)
            ->whereNotIn('id', $overrides->pluck('feature_id'))
            ->orderBy('key')
            ->get();

        return view('features::pages.tenant-overrides', [
            'overrides' => $overrides,
            'availableFeatures' => $availableFeatures,
            'effectiveFeatures' => app(ResolveTenantFeaturesAction::class)->execute($tenant),
        ]);
    }
}

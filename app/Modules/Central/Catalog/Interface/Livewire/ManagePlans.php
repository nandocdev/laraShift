<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Interface\Livewire;

use App\Modules\Central\Catalog\Application\Services\CatalogFeatureRegistry;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class ManagePlans extends Component
{
    use WithPagination;

    public ?string $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $slug = '';

    public bool $slugLocked = true;

    public string $priceMonthly = '0.00';

    public string $priceYearly = '0.00';

    public string $currency = 'USD';

    public string $interval = 'month';

    public bool $isActive = true;

    public bool $isCustom = false;

    /** @var list<string> */
    public array $displayFeatures = [];

    /** @var array<string, string> metric => limit ('' = unlimited) */
    public array $quotas = [];

    public string $gatewayClave = '';

    public string $gatewayDlocal = '';

    private function registry(): CatalogFeatureRegistry
    {
        return app(CatalogFeatureRegistry::class);
    }

    public function updatedName(): void
    {
        if ($this->editingId === null && $this->slugLocked) {
            $this->slug = (string) Str::slug($this->name);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugLocked = false;
        $this->slug = (string) Str::slug($this->slug);
    }

    public function create(): void
    {
        $this->cancelEdit();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $plan = Plan::findOrFail($id);

        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->slugLocked = false;
        $this->priceMonthly = number_format($plan->price_monthly / 100, 2, '.', '');
        $this->priceYearly = number_format($plan->price_yearly / 100, 2, '.', '');
        $this->currency = $plan->currency;
        $this->interval = $plan->interval;
        $this->isActive = (bool) $plan->is_active;
        $this->isCustom = (bool) ($plan->is_custom ?? false);
        $this->displayFeatures = array_values(array_intersect(
            $this->registry()->featureKeys(),
            is_array($plan->features['display_features'] ?? null) ? $plan->features['display_features'] : []
        ));
        $this->quotas = [];
        foreach ($this->registry()->quotaMetrics() as $metric) {
            $value = $plan->features['quotas'][$metric] ?? null;
            $this->quotas[$metric] = is_numeric($value) ? (string) (int) $value : '';
        }
        $this->gatewayClave = (string) ($plan->features['gateway_ids']['clave'] ?? '');
        $this->gatewayDlocal = (string) ($plan->features['gateway_ids']['dlocal'] ?? '');
        $this->showForm = true;
    }

    public function cancelEdit(): void
    {
        $this->showForm = false;
        $this->reset([
            'editingId', 'name', 'slug', 'slugLocked', 'priceMonthly', 'priceYearly',
            'currency', 'interval', 'isActive', 'isCustom', 'displayFeatures',
            'quotas', 'gatewayClave', 'gatewayDlocal',
        ]);
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:60', Rule::unique('plans', 'slug')->ignore($this->editingId)],
            'priceMonthly' => ['required', 'numeric', 'min:0', 'max:999999'],
            'priceYearly' => ['required', 'numeric', 'min:0', 'max:999999'],
            'currency' => ['required', 'alpha', 'size:3'],
            'interval' => ['required', 'in:month,year'],
            'isActive' => ['boolean'],
            'isCustom' => ['boolean'],
            'displayFeatures' => ['array'],
            'displayFeatures.*' => ['in:'.implode(',', $this->registry()->featureKeys())],
            'quotas' => ['array'],
            'quotas.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'gatewayClave' => ['nullable', 'string', 'max:100'],
            'gatewayDlocal' => ['nullable', 'string', 'max:100'],
        ]);

        $features = [
            'display_features' => array_values($validated['displayFeatures'] ?? []),
            'gateway_ids' => array_filter([
                'clave' => trim($validated['gatewayClave'] ?? ''),
                'dlocal' => trim($validated['gatewayDlocal'] ?? ''),
            ]),
            'quotas' => collect($validated['quotas'] ?? [])
                ->reject(fn ($value) => $value === null || $value === '')
                ->map(fn ($value) => (int) $value)
                ->all(),
        ];

        $attributes = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'price_monthly' => (int) round((float) $validated['priceMonthly'] * 100),
            'price_yearly' => (int) round((float) $validated['priceYearly'] * 100),
            'currency' => strtoupper($validated['currency']),
            'interval' => $validated['interval'],
            'features' => $features,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'is_custom' => (bool) ($validated['isCustom'] ?? false),
        ];

        if ($this->editingId) {
            // The slug is the identity tenants reference: never change it on edit.
            unset($attributes['slug']);
            Plan::findOrFail($this->editingId)->update($attributes);
            session()->flash('status', __('Plan updated.'));
        } else {
            Plan::create($attributes);
            session()->flash('status', __('Plan created.'));
        }

        $this->cancelEdit();
        $this->dispatch('plan-modal-close');
    }

    public function toggleActive(string $id): void
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
    }

    public function delete(string $id): void
    {
        $plan = Plan::findOrFail($id);

        // Un plan con suscripciones no se archiva: se desactiva para que
        // los tenants existentes lo conserven y nadie nuevo lo contrate.
        if ($this->subscriptionCount($id) > 0) {
            $plan->update(['is_active' => false]);
            if ($this->editingId === $id) {
                $this->cancelEdit();
            }
            session()->flash('status', __('Plan has subscriptions: archived is blocked, deactivated instead.'));
            $this->dispatch('plan-modal-close');

            return;
        }

        $plan->delete();
        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
        session()->flash('status', __('Plan archived.'));
        $this->dispatch('plan-modal-close');
    }

    public function duplicate(string $id): void
    {
        $plan = Plan::findOrFail($id);

        $slug = $plan->slug.'-copy';
        for ($i = 2; Plan::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $plan->slug.'-copy-'.$i;
        }

        Plan::create([
            'name' => $plan->name.' (copy)',
            'slug' => $slug,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'features' => $plan->features,
            'is_active' => false,
            'is_custom' => (bool) ($plan->is_custom ?? false),
        ]);

        session()->flash('status', __('Plan duplicated as :slug (inactive).', ['slug' => $slug]));
    }

    public function render(): View
    {
        return view('catalog::livewire.manage-plans', [
            'plans' => Plan::latest()->paginate(15),
            'featureLabels' => $this->registry()->featureLabels(),
            'quotaLabels' => $this->registry()->quotaLabels(),
            'tenantCounts' => $this->tenantCounts(),
        ]);
    }

    /**
     * @return array<string, int> plan slug => tenants
     */
    private function tenantCounts(): array
    {
        try {
            return DB::table('tenants')
                ->selectRaw('plan_id, count(*) as total')
                ->groupBy('plan_id')
                ->pluck('total', 'plan_id')
                ->map(fn ($total) => (int) $total)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function subscriptionCount(string $planId): int
    {
        try {
            return DB::table('subscriptions')->where('plan_id', $planId)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}

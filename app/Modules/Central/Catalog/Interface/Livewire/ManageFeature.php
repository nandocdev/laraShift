<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Interface\Livewire;

use App\Modules\Central\Catalog\Domain\Models\Feature;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManageFeature extends Component
{
    public ?Feature $feature = null;

    public bool $isEditing = false;

    public string $key = '';

    public string $name = '';

    public string $description = '';

    public string $module = '';

    public bool $is_active = true;

    public function mount(?Feature $feature = null): void
    {
        if ($feature && $feature->exists) {
            $this->feature = $feature;
            $this->isEditing = true;
            $this->key = $feature->key;
            $this->name = $feature->name;
            $this->description = $feature->description ?? '';
            $this->module = $feature->module ?? '';
            $this->is_active = $feature->is_active;
        }
    }

    public function updatedKey($value): void
    {
        $this->key = Str::lower(Str::slug($value, '.'));
    }

    public function save(): void
    {
        Gate::authorize('features:manage');

        $this->validate([
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+\.[a-z0-9_]+$/',
                'unique:features,key,'.($this->feature->id ?? 'NULL').',id',
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ], [
            'key.regex' => __('The key must follow the format module.action (e.g. auth.mfa_enforce)'),
        ]);

        $attributes = [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditing) {
            $this->feature->update($attributes);
            activity('catalog')->performedOn($this->feature)->log('feature_updated');
            session()->flash('status', __('Feature updated.'));
        } else {
            $attributes['id'] = Str::uuid()->toString();
            $feature = Feature::create($attributes);
            activity('catalog')->performedOn($feature)->log('feature_created');
            session()->flash('status', __('Feature created.'));
        }

        // Invalidate cache for tenants that have this feature via their plan (C001)
        $featureKey = $this->key;
        $planIds = DB::table('plan_features')
            ->join('features', 'features.id', '=', 'plan_features.feature_id')
            ->where('features.key', $featureKey)
            ->pluck('plan_features.plan_id')
            ->toArray();

        if ($planIds !== []) {
            $planSlugs = Plan::whereIn('id', $planIds)->pluck('slug')->toArray();
            $allPlanIds = array_merge($planIds, $planSlugs);
            Tenant::whereIn('plan_id', $allPlanIds)->chunkById(200, function ($tenants) {
                foreach ($tenants as $tenant) {
                    Cache::forget("tenant:{$tenant->id}:features");
                }
            });
        }

        $this->redirect(route('central.features.index'), navigate: true);
    }

    public function delete(): void
    {
        Gate::authorize('features:manage');

        if (! $this->feature) {
            return;
        }

        // Perform soft delete
        $this->feature->delete();
        activity('catalog')->performedOn($this->feature)->log('feature_retired');

        session()->flash('status', __('Feature retired. Historical data remains valid.'));
        $this->redirect(route('central.features.index'), navigate: true);
    }

    public function render(): View
    {
        return view('features::pages.manage-feature');
    }
}

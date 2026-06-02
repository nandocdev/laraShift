<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Actions\UpsertPlanAction;
use App\Modules\Central\Billing\Actions\DeletePlanAction;
use App\Modules\Central\Billing\DTOs\PlanData;
use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManagePlan extends Component
{
    public ?Plan $plan = null;
    public bool $isEditing = false;

    public string $name = '';
    public string $slug = '';
    public float $price_monthly = 0.0;
    public float $price_yearly = 0.0;
    public bool $is_active = true;
    public string $stripe_id = '';
    
    // Features structure
    public int $quota_branches = 1;
    public int $quota_staff = 3;
    public int $quota_bookings = 100;
    public string $display_features = ''; // Comma separated

    public function mount(?Plan $plan = null): void
    {
        if ($plan && $plan->exists) {
            $this->plan = $plan;
            $this->isEditing = true;
            $this->name = $plan->name;
            $this->slug = $plan->slug;
            $this->price_monthly = $plan->price_monthly / 100;
            $this->price_yearly = $plan->price_yearly / 100;
            $this->is_active = $plan->is_active;
            
            $features = $plan->features ?? [];
            $this->stripe_id = $features['stripe_id'] ?? '';
            $this->quota_branches = $features['quotas']['branches'] ?? 1;
            $this->quota_staff = $features['quotas']['staff'] ?? 3;
            $this->quota_bookings = $features['quotas']['bookings'] ?? 100;
            $this->display_features = implode(', ', $features['display_features'] ?? []);
        }
    }

    public function save(UpsertPlanAction $action): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|alpha_dash|max:100|unique:plans,slug,' . ($this->plan->id ?? 'NULL') . ',id',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $features = [
            'stripe_id' => $this->stripe_id ?: null,
            'display_features' => array_map('trim', explode(',', $this->display_features)),
            'quotas' => [
                'branches' => (int) $this->quota_branches,
                'staff' => (int) $this->quota_staff,
                'bookings' => (int) $this->quota_bookings,
            ],
        ];

        $data = new PlanData(
            name: $this->name,
            slug: $this->slug,
            price_monthly: (int) ($this->price_monthly * 100),
            price_yearly: (int) ($this->price_yearly * 100),
            is_active: $this->is_active,
            features: $features,
        );

        try {
            $action->execute($data, $this->plan);
            
            session()->flash('status', $this->isEditing ? __('Plan updated.') : __('Plan created.'));
            $this->redirect(route('central.billing.plans'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function delete(DeletePlanAction $action): void
    {
        if (! $this->plan) return;

        try {
            $action->execute($this->plan);
            session()->flash('status', __('Plan deleted.'));
            $this->redirect(route('central.billing.plans'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('billing::pages.manage-plan');
    }
}

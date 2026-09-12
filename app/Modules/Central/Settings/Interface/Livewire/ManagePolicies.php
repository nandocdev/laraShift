<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Interface\Livewire;

use App\Modules\Central\Settings\Infrastructure\Services\PlatformPolicies;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManagePolicies extends Component
{
    public int $fraudThreshold = 80;

    public string $secopsEmail = '';

    public int $staleMinutes = 30;

    public function mount(): void
    {
        $this->fraudThreshold = PlatformPolicies::fraudThreshold();
        $this->secopsEmail = PlatformPolicies::secopsEmail() ?? '';
        $this->staleMinutes = PlatformPolicies::staleProvisioningMinutes();
    }

    public function save(): void
    {
        Gate::authorize('policies:manage');

        $this->validate([
            'fraudThreshold' => 'required|integer|min:1|max:100',
            'secopsEmail' => 'nullable|email|max:255',
            'staleMinutes' => 'required|integer|min:1|max:1440',
        ]);

        PlatformPolicies::set(PlatformPolicies::FRAUD_THRESHOLD, $this->fraudThreshold, 'int');
        PlatformPolicies::set(PlatformPolicies::SECOPS_EMAIL, $this->secopsEmail);
        PlatformPolicies::set(PlatformPolicies::STALE_MINUTES, $this->staleMinutes, 'int');

        activity('settings')
            ->causedBy(auth('central')->user())
            ->withProperties([
                'fraud_threshold' => $this->fraudThreshold,
                'stale_minutes' => $this->staleMinutes,
            ])
            ->log('policies_updated');

        session()->flash('status', __('Platform policies updated successfully.'));
    }

    public function render(): View
    {
        return view('settings::pages.global-policies');
    }
}

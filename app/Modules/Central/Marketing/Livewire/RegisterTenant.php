<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Livewire;

use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.marketing')]
class RegisterTenant extends Component
{
    public string $name = '';
    public string $email = '';
    public string $company = '';
    public string $slug = '';
    public string $password = '';
    public string $plan_id = 'free';

    protected bool $autoGenerateSlug = true;

    public function mount(): void
    {
        $this->plan_id = request()->query('plan', 'free');
    }

    public function updatedCompany(): void
    {
        if ($this->autoGenerateSlug) {
            $this->slug = Str::slug($this->company);
        }
    }

    public function updatedSlug(): void
    {
        // If the user manually edits the slug, stop auto-generating it
        $this->autoGenerateSlug = false;
        // Keep it URL friendly
        $this->slug = Str::slug($this->slug);
    }

    public function register(CreateTenantAction $action): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:central_users,email',
            'company' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9-]+$/',
                'not_in:www,api,admin,central,app',
                'unique:tenants,slug'
            ],
            'password' => 'required|string|min:12',
            'plan_id' => 'required|exists:plans,slug',
        ]);

        $tenant = $action->execute(new CreateTenantData(
            name: $this->company,
            slug: $this->slug,
            email: $this->email,
            plan_id: $this->plan_id,
            password: $this->password
        ));

        // After creation, redirect to their new tenant login page
        $domain = $this->slug . '.' . config('tenancy.central_domain');
        $protocol = app()->environment('local') ? 'http' : 'https';
        
        $this->redirect("$protocol://$domain/auth/login", navigate: false);
    }

    public function render(): View
    {
        return view('marketing::pages.register-tenant', [
            'plans' => PlanManager::all(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Support\ReservedSlugs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Wizard de registro multi-step con integración de pago.
 *
 * Step 1: Datos de organización (nombre, email, compañía, slug, password)
 * Step 2: Selección visual de plan (cards con features y precios)
 * Step 3: Resumen y Pago
 *
 * [RIESGOS]
 * - Race condition en slug: validación `unique:tenants,slug` puede fallar si dos
 *   usuarios registran el mismo slug simultáneamente. Mitigado: constraint DB unique.
 */
#[Layout('layouts.marketing')]
class RegisterTenant extends Component
{
    // Step 1: Organization
    public string $name = '';
    public string $email = '';
    public string $company = '';
    public string $slug = '';
    public string $password = '';

    // Step 2: Plan
    public string $plan_id = 'free';

    // Wizard state
    public int $step = 1;
    protected bool $autoGenerateSlug = true;

    /**
     * @var array<string, array> Reglas de validación por step.
     */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255|unique:central_users,email',
                'company'  => 'required|string|max:255',
                'slug'     => [
                    'required', 'string', 'max:63',
                    'regex:/^[a-z0-9-]+$/',
                    'not_in:' . implode(',', ReservedSlugs::$list),
                    'unique:tenants,slug',
                ],
                'password' => ['required', 'string', Password::defaults()],
            ],
            2 => [
                'plan_id' => 'required|exists:plans,slug',
            ],
            3 => [],
            default => [],
        };
    }

    public function mount(): void
    {
        $planFromQuery = request()->query('plan', '');

        if ($planFromQuery && Plan::where('slug', $planFromQuery)->exists()) {
            $this->plan_id = $planFromQuery;
        }
    }

    public function updatedCompany(): void
    {
        if ($this->autoGenerateSlug) {
            $this->slug = Str::slug($this->company);
        }
    }

    public function updatedSlug(): void
    {
        $this->autoGenerateSlug = false;
        $this->slug = Str::slug($this->slug);
    }

    /**
     * Avanza al siguiente step con validación parcial.
     */
    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step));

        if ($this->step < 3) {
            $this->step++;
        }
    }

    /**
     * Retrocede al step anterior.
     */
    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    /**
     * Selecciona un plan desde las cards de step 2.
     */
    public function selectPlan(string $slug): void
    {
        $this->plan_id = $slug;
    }

    /**
     * Ejecuta el registro completo: provisioning + billing (via redirect).
     */
    public function register(CreateTenantAction $action): void
    {
        $this->validate($this->rulesForStep($this->step));

        $tenant = $action->execute(new CreateTenantData(
            name: $this->company,
            slug: $this->slug,
            email: $this->email,
            plan_id: $this->plan_id,
            password: $this->password,
            payment_token: null,
        ));

        $domain = $this->slug . '.' . config('tenancy.central_domain');
        $baseUrl = config('app.url');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $portSuffix = $port ? ":$port" : '';

        // If it's a paid plan, redirect to the hosted checkout page within the tenant context
        if (! $this->isPlanFree()) {
            $checkoutUrl = "$scheme://$domain$portSuffix/billing/checkout/hosted/{$tenant->id}/{$this->selectedPlan->id}";

            $this->redirect($checkoutUrl, navigate: false);
            return;
        }

        $this->redirect("$scheme://$domain$portSuffix/auth/login", navigate: false);
    }

    /**
     * Determina si el plan seleccionado es gratuito.
     */
    public function isPlanFree(): bool
    {
        $plan = Plan::where('slug', $this->plan_id)->first();

        return ! $plan || ! $plan->price_monthly->isPositive();
    }

    /**
     * Obtiene el plan seleccionado actualmente.
     */
    public function getSelectedPlanProperty(): ?Plan
    {
        return Plan::where('slug', $this->plan_id)->first();
    }

    public function render(): View
    {
        return view('marketing::pages.register-tenant', [
            'plans'        => PlanManager::all(),
            'selectedPlan' => $this->selectedPlan,
            'isPlanFree'   => $this->isPlanFree(),
        ]);
    }
}

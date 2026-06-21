<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Support\ReservedSlugs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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

    // Step 3: Payment
    public ?string $payment_token = null;

    public string $billing_option = 'trial_no_card';

    public string $country = 'UY';

    // Wizard state
    public int $step = 1;

    public bool $autoGenerateSlug = true;

    public bool $loading = false;

    public ?string $error = null;

    public bool $paymentAlreadyApproved = false;

    /**
     * @var array<string, array> Reglas de validación por step.
     */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:central_users,email',
                'company' => 'required|string|max:255',
                'country' => 'required|string|size:2|in:UY,EC,AR,BR,CL,CO,MX,PE,PA',
                'slug' => [
                    'required', 'string', 'max:63',
                    'regex:/^[a-z0-9-]+$/',
                    'not_in:'.implode(',', ReservedSlugs::$list),
                    'unique:tenants,slug',
                ],
                'password' => [
                    'required',
                    'string',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
            ],
            2 => [
                'plan_id' => 'required|exists:plans,slug',
            ],
            3 => [
                'billing_option' => 'required|in:trial_no_card,trial_with_card,pay_now',
                'payment_token' => ($this->isPlanFree() || $this->billing_option === 'trial_no_card' || $this->paymentAlreadyApproved) ? 'nullable|string' : 'required|string',
            ],
            default => [],
        };
    }

    public function mount(): void
    {
        $planFromQuery = request()->query('plan', '');

        if ($planFromQuery && Plan::where('slug', $planFromQuery)->exists()) {
            $this->plan_id = $planFromQuery;
        }

        $this->paymentAlreadyApproved = $this->isPaymentAlreadyApproved();
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
        $rules = $this->rulesForStep($this->step);

        if (! empty($rules)) {
            $this->validate($rules);
        }

        if ($this->step === 1) {
            $lockKey = 'reserved_slug_'.$this->slug;
            $currentLock = Cache::get($lockKey);

            if ($currentLock && $currentLock !== $this->email) {
                $this->addError('slug', __('This workspace URL is temporarily reserved by another user.'));

                return;
            }

            Cache::put($lockKey, $this->email, now()->addMinutes(15));
        }

        if ($this->step === 2) {
            $this->paymentAlreadyApproved = $this->isPaymentAlreadyApproved();
        }

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

    public function register(CreateTenantAction $action): void
    {
        $this->loading = true;
        $this->error = null;

        // Validamos todos los pasos anteriores para asegurar integridad antes de crear el tenant
        $allRules = array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3)
        );

        try {
            $this->validate($allRules);

            // Final lock check to prevent race conditions during checkout
            $lockKey = 'reserved_slug_'.$this->slug;
            $currentLock = Cache::get($lockKey);
            if ($currentLock && $currentLock !== $this->email) {
                throw new \Exception(__('This workspace URL is temporarily reserved by another user.'));
            }

            $tenant = $action->execute(new CreateTenantData(
                name: $this->company,
                slug: $this->slug,
                email: $this->email,
                plan_id: $this->plan_id,
                password: $this->password,
                payment_token: $this->payment_token,
                billing_option: $this->billing_option,
                country: $this->country,
            ));

            // Release lock on success
            Cache::forget($lockKey);

            $domain = $this->slug.'.'.config('tenancy.central_domain');
            $baseUrl = config('app.url');
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';
            $port = parse_url($baseUrl, PHP_URL_PORT);
            $portSuffix = $port ? ":$port" : '';

            // Immediate login redirection
            $this->redirect("$scheme://$domain$portSuffix/auth/login", navigate: false);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->paymentAlreadyApproved = $this->isPaymentAlreadyApproved();
            $this->error = $e->getMessage();
            $this->addError('payment_token', $e->getMessage());
        } finally {
            $this->loading = false;
        }
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

    public function isPaymentAlreadyApproved(): bool
    {
        if ($this->isPlanFree()) {
            return false;
        }

        if (empty($this->slug) || empty($this->email)) {
            return false;
        }

        $checkoutSlug = 'checkout_'.md5($this->slug.$this->email);

        return Payment::where('slug', $checkoutSlug)
            ->where('status', 'approved')
            ->exists();
    }

    public function render(): View
    {
        $this->paymentAlreadyApproved = $this->isPaymentAlreadyApproved();

        return view('marketing::pages.register-tenant', [
            'plans' => PlanManager::all(),
            'selectedPlan' => $this->selectedPlan,
            'isPlanFree' => $this->isPlanFree(),
        ]);
    }
}

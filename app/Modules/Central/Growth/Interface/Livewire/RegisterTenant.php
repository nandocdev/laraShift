<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Interface\Livewire;

use App\Modules\Central\Growth\Application\Actions\FraudScoringAction;
use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Support\ReservedSlugs;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Wizard de registro en 2 pasos (sin planes: el billing se reconstruye desde cero).
 *
 * Step 1: Datos de organización (nombre, email, compañía, slug, password)
 * Step 2: Confirmación y creación
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

    public string $honeypot = '';

    // Wizard state
    public int $step = 1;

    public bool $autoGenerateSlug = true;

    /**
     * @var array<string, array> Reglas de validación por step.
     */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:tenants,email',
                'company' => 'required|string|max:255',
                'slug' => [
                    'required', 'string', 'max:63',
                    'regex:/^[a-z0-9-]+$/',
                    'not_in:'.implode(',', ReservedSlugs::all()),
                    'unique:tenants,slug',
                ],
                'password' => ['required', 'string', Password::defaults()],
                'honeypot' => 'prohibited',
            ],
            2 => [],
            default => [],
        };
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
            try {
                $this->validate($rules);
            } catch (ValidationException $e) {
                if ($this->step === 1) {
                    $this->reset('password');
                }
                throw $e;
            }
        }

        if ($this->step < 2) {
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
     * Ejecuta el registro completo: validación + provisioning.
     */
    public function register(CreateTenantAction $action, FraudScoringAction $fraudScoring): void
    {
        try {
            // Validamos todos los pasos anteriores para asegurar integridad antes de crear el tenant
            $allRules = array_merge(
                $this->rulesForStep(1),
                $this->rulesForStep(2)
            );

            try {
                $this->validate($allRules);
            } catch (ValidationException $e) {
                $this->reset('password');
                throw $e;
            }

            if ($this->honeypot !== '') {
                $this->addError('honeypot', __('Spam detected.'));

                return;
            }

            // Evaluate fraud signals synchronously from the current request.
            // Runs before tenant creation so quarantine status can be embedded in
            // CreateTenantData and carried through to ProvisionTenantJob.
            $fraudSignals = $fraudScoring->evaluate(request(), $this->email);
            $isQuarantined = $fraudSignals->exceedsThreshold();

            $tenant = $action->execute(new CreateTenantData(
                name: strip_tags($this->company),
                slug: strtolower(Str::slug($this->slug)),
                email: $this->email,
                password: $this->password,
                status: $isQuarantined ? 'quarantine' : 'active',
                fraud_signals_payload: $isQuarantined ? $fraudSignals->toArray() : null,
            ));

            // Quarantined tenants: do not proceed to the workspace.
            // The pipeline will notify SecOps; we show a neutral holding message.
            if ($isQuarantined) {
                session()->flash('status', __('Your registration is under review. You will be notified by email.'));
                $this->redirect(route('central.home'), navigate: false);

                return;
            }

            $redirectUrl = tenant_route(
                $tenant->domains->first()?->domain ?? "{$this->slug}.".config('tenancy.central_domain'),
                'login',
            );

            $this->redirect($redirectUrl, navigate: false);
        } catch (UniqueConstraintViolationException $e) {
            $this->addError('slug', __('This organization URL is already taken. Please choose another.'));
        } catch (\RuntimeException $e) {
            // CreateTenantAction maps UniqueConstraintViolationException -> RuntimeException "slug just taken"
            $this->addError('slug', $e->getMessage());
        } finally {
            $this->reset('password');
        }
    }

    public function render(): View
    {
        return view('marketing::pages.register-tenant');
    }
}

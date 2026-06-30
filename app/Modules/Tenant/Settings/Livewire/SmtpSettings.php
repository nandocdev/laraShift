<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Tenant\Settings\Actions\UpdateTenantSmtpAction;
use App\Modules\Tenant\Settings\DTOs\SmtpConfigData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use App\Modules\Tenant\Settings\Services\TenantMailerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SmtpSettings extends Component
{
    public string $smtp_host = '';

    public int $smtp_port = 587;

    public string $smtp_user = '';

    public string $smtp_password = '';

    public string $smtp_from_email = '';

    public string $smtp_from_name = '';

    public string $test_email = '';

    public ?string $test_status = null;

    public ?string $test_error = null;

    public function mount(): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();

        if ($settings) {
            $this->smtp_host = $settings->smtp_host ?? '';
            $this->smtp_port = $settings->smtp_port ?? 587;
            $this->smtp_user = $settings->smtp_user ?? '';
            // We don't populate password for security, unless empty
            $this->smtp_from_email = $settings->smtp_from_email ?? '';
            $this->smtp_from_name = $settings->smtp_from_name ?? '';
        }
    }

    public function save(UpdateTenantSmtpAction $action): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        $this->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'smtp_user' => 'required|string',
            'smtp_from_email' => 'required|email',
            'smtp_from_name' => 'required|string',
        ]);

        $action->execute(new SmtpConfigData(
            host: $this->smtp_host,
            port: $this->smtp_port,
            user: $this->smtp_user,
            password: ! empty($this->smtp_password) ? $this->smtp_password : null,
            fromEmail: $this->smtp_from_email,
            fromName: $this->smtp_from_name,
        ));

        session()->flash('status', __('SMTP settings updated successfully. Connection must be verified.'));
    }

    public function testConnection(TenantMailerService $mailerService): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
        Gate::authorize('update', $settings);

        $this->validate([
            'test_email' => 'required|email',
        ]);

        $this->test_status = 'testing';
        $this->test_error = null;

        try {
            $dbPassword = $settings->smtp_password ? decrypt($settings->smtp_password) : '';
            $password = ! empty($this->smtp_password) ? $this->smtp_password : $dbPassword;

            $config = new SmtpConfigData(
                host: $this->smtp_host,
                port: $this->smtp_port,
                user: $this->smtp_user,
                password: $password,
                fromEmail: $this->smtp_from_email,
                fromName: $this->smtp_from_name,
            );

            $mailerService->withConfig($config, function ($mailer) {
                $mailer->raw(__('This is a test email from LaraShift to verify your SMTP configuration.'), function ($message) {
                    $message->to($this->test_email)
                        ->from($this->smtp_from_email, $this->smtp_from_name)
                        ->subject(__('LaraShift SMTP Test'));
                });
            });

            $settings->update(['smtp_verified' => true]);
            $this->test_status = 'success';
        } catch (\Exception $e) {
            $this->test_status = 'failed';
            $this->test_error = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.smtp-settings');
    }
}

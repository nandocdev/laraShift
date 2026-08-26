<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Interface\Livewire;

use App\Modules\Tenant\Experience\Application\Actions\GetTenantSmtpSettings;
use App\Modules\Tenant\Experience\Application\Actions\MarkTenantSmtpVerified;
use App\Modules\Tenant\Experience\Application\DTO\SmtpConfigData;
use App\Modules\Tenant\Integrations\Application\Actions\UpdateTenantSmtp;
use App\Modules\Tenant\Integrations\Application\Services\TenantMailerService;
use Illuminate\Contracts\View\View;
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

    public function mount(GetTenantSmtpSettings $action): void
    {
        $settings = $action->execute();

        if ($settings) {
            $this->smtp_host = $settings->host;
            $this->smtp_port = $settings->port;
            $this->smtp_user = $settings->user;
            // We don't populate password for security, unless empty
            $this->smtp_from_email = $settings->fromEmail;
            $this->smtp_from_name = $settings->fromName;
        }
    }

    public function save(UpdateTenantSmtp $action): void
    {
        $this->authorizeManagement();

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

    public function testConnection(TenantMailerService $mailerService, GetTenantSmtpSettings $getSettings, MarkTenantSmtpVerified $markVerified): void
    {
        $this->authorizeManagement();

        $this->validate([
            'test_email' => 'required|email',
        ]);

        $this->test_status = 'testing';
        $this->test_error = null;

        try {
            $stored = $getSettings->execute();
            $password = ! empty($this->smtp_password) ? $this->smtp_password : ($stored?->plainPassword ?? '');

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

            $markVerified->execute();
            $this->test_status = 'success';
        } catch (\Exception $e) {
            $this->test_status = 'failed';
            $this->test_error = $e->getMessage();
        }
    }

    public function render(GetTenantSmtpSettings $action): View
    {
        return view('settings-tenant::livewire.smtp-settings', [
            'smtpVerified' => $action->execute()?->verified ?? false,
        ]);
    }

    private function authorizeManagement(): void
    {
        app(EnsureUserCanManageTenantSettings::class)->execute();
    }
}

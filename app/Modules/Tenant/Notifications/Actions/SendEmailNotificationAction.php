<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Actions;

use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Notifications\Models\NotificationTemplate;
use App\Modules\Tenant\Settings\DTOs\SmtpConfigData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use App\Modules\Tenant\Settings\Services\TenantMailerService;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final readonly class SendEmailNotificationAction
{
    public function __construct(
        private TenantMailerService $mailer,
    ) {}

    public function execute(User $user, string $key, array $payload = []): bool
    {
        $template = NotificationTemplate::where('key', $key)
            ->where('channel', 'email')
            ->where('is_active', true)
            ->first();

        if (! $template) {
            Log::info('No active email template found for notification', [
                'tenant_id' => tenant('id'),
                'key' => $key,
            ]);

            return false;
        }

        $subject = $this->interpolate($template->subject ?? __("notification.{$key}.subject"), $payload);
        $body = $this->interpolate($template->body ?? '', $payload);

        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();

        if ($settings && $settings->smtp_host && $settings->smtp_user) {
            $smtpConfig = new SmtpConfigData(
                host: $settings->smtp_host,
                port: (int) ($settings->smtp_port ?? 587),
                user: $settings->smtp_user,
                password: $settings->smtp_password,
                fromEmail: $settings->smtp_from_email,
                fromName: $settings->smtp_from_name ?? tenant()->name,
            );

            $this->mailer->withConfig($smtpConfig, function ($mailer) use ($user, $subject, $body) {
                $mailer->send([], function (Message $message) use ($user, $subject, $body) {
                    $message->to($user->email, $user->name)
                        ->subject($subject)
                        ->setBody($body, 'text/html');
                });
            });

            return true;
        }

        Mail::send([], function (Message $message) use ($user, $subject, $body) {
            $message->to($user->email, $user->name)
                ->subject($subject)
                ->setBody($body, 'text/html');
        });

        return true;
    }

    private function interpolate(string $text, array $payload): string
    {
        $replacements = [];
        foreach ($payload as $key => $value) {
            $replacements["{{$key}}"] = (string) $value;
        }

        return strtr($text, $replacements);
    }
}

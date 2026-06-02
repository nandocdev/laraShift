<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CentralResetPasswordNotification extends ResetPasswordNotification
{
    /**
     * Get the reset password URL for the given token.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function resetUrl($notifiable): string
    {
        return url(route('central.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}

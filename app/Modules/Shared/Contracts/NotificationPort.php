<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface NotificationPort
{
    public function send(string $recipient, string $subject, string $body, array $attachments = []): bool;

    public function sendTemplate(string $recipient, string $template, array $data = []): bool;

    public function sendBulk(array $recipients, string $subject, string $body): int;
}

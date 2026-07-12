<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\DTOs;

final readonly class PayerData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $documentId,
        public ?string $documentType = null,
        public ?string $phone = null,
        public ?string $userReference = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->documentId,
            'document_type' => $this->documentType,
            'phone' => $this->phone,
            'user_reference' => $this->userReference,
        ], static fn ($value) => $value !== null);
    }
}

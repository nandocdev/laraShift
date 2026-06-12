<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\DTOs;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;

final class BrandingData extends Data
{
    public function __construct(
        public string $name,
        public string $primaryColor,
        public string $themePreset,
        public bool $mfaRequired,
        public ?UploadedFile $logo = null,
    ) {}
}

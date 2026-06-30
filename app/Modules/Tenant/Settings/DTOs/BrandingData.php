<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\DTOs;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

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

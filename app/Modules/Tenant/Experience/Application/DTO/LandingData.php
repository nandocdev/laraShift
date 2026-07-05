<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\DTO;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class LandingData extends Data
{
    public function __construct(
        public string $title,
        public string $slug,
        public array $theme = [],
        /** @var DataCollection<BlockData> */
        public ?DataCollection $blocks = null,
    ) {}
}

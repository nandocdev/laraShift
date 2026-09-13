<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Domain\Enums;

enum ResourceType: string
{
    case Staff = 'staff';
    case Room = 'room';
    case Equipment = 'equipment';
}

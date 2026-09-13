<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Application\Actions;

use App\Modules\Product\Locations\Application\DTO\LocationData;
use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class CreateLocation
{
    public function execute(LocationData $data): Location
    {
        return DB::transaction(function () use ($data) {
            $location = Location::create([
                'tenant_id' => tenant('id'),
                'name' => $data->name,
                'slug' => $data->slug,
                'address' => $data->address,
                'phone' => $data->phone,
                'email' => $data->email,
                'timezone' => $data->timezone,
                'is_active' => $data->isActive,
            ]);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::LOCATION_CREATED,
                    resource: 'locations',
                    resourceId: $location->id,
                    metadata: ['name' => $location->name, 'slug' => $location->slug]
                )
            );

            return $location;
        });
    }
}

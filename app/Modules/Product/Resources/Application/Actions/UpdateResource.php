<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Application\Actions;

use App\Modules\Product\Resources\Application\DTO\ResourceData;
use App\Modules\Product\Resources\Domain\Enums\ResourceType;
use App\Modules\Product\Resources\Domain\Models\Resource;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class UpdateResource
{
    public function __construct(private CreateResource $createResource) {}

    public function execute(Resource $resource, ResourceData $data): Resource
    {
        return DB::transaction(function () use ($resource, $data) {
            $locationId = $this->createResource->resolveLocationId($data->locationId);
            $serviceIds = $this->createResource->resolveServiceIds($data->serviceIds);

            $resource->update([
                'location_id' => $locationId,
                'type' => ResourceType::from($data->type),
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'capacity' => $data->capacity,
                'skills' => $data->skills,
                'rate_cents' => $data->rateCents,
                'currency' => $data->currency,
                'is_active' => $data->isActive,
            ]);

            // Reassigning the location is the RF12.3 transfer: it is audited
            // as part of the update, no parallel state machine.
            $resource->services()->syncWithPivotValues($serviceIds, ['tenant_id' => tenant('id')]);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::RESOURCE_UPDATED,
                    resource: 'resources',
                    resourceId: $resource->id,
                    metadata: ['name' => $resource->name, 'location_id' => $resource->location_id]
                )
            );

            return $resource->refresh();
        });
    }
}

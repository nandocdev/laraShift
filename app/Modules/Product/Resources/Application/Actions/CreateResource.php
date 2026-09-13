<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Application\Actions;

use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Resources\Application\DTO\ResourceData;
use App\Modules\Product\Resources\Domain\Enums\ResourceType;
use App\Modules\Product\Resources\Domain\Models\Resource;
use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateResource
{
    public function execute(ResourceData $data): Resource
    {
        return DB::transaction(function () use ($data) {
            $locationId = $this->resolveLocationId($data->locationId);
            $serviceIds = $this->resolveServiceIds($data->serviceIds);

            $resource = Resource::create([
                'tenant_id' => tenant('id'),
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

            $resource->services()->syncWithPivotValues($serviceIds, ['tenant_id' => tenant('id')]);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::RESOURCE_CREATED,
                    resource: 'resources',
                    resourceId: $resource->id,
                    metadata: ['name' => $resource->name, 'slug' => $resource->slug, 'type' => $resource->type->value]
                )
            );

            return $resource;
        });
    }

    public function resolveLocationId(?string $locationId): ?string
    {
        if ($locationId === null) {
            return null;
        }

        $exists = Location::where('tenant_id', tenant('id'))
            ->where('id', $locationId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'locationId' => __('The selected location does not belong to this business.'),
            ]);
        }

        return $locationId;
    }

    /**
     * @param  array<int, string>  $serviceIds
     * @return array<int, string>
     */
    public function resolveServiceIds(array $serviceIds): array
    {
        $ids = array_values(array_unique($serviceIds));

        if ($ids === []) {
            return [];
        }

        $count = Service::where('tenant_id', tenant('id'))->whereIn('id', $ids)->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'serviceIds' => __('One or more services do not belong to this business.'),
            ]);
        }

        return $ids;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\Actions;

use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Services\Application\DTO\ServiceData;
use App\Modules\Product\Services\Domain\Enums\DepositType;
use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Product\Services\Domain\Models\ServiceCategory;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateService
{
    public function execute(ServiceData $data): Service
    {
        return DB::transaction(function () use ($data) {
            $categoryId = $this->resolveCategoryId($data->categoryId);
            $locationIds = $this->resolveLocationIds($data->locationIds);

            $service = Service::create([
                'tenant_id' => tenant('id'),
                'category_id' => $categoryId,
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'duration_minutes' => $data->durationMinutes,
                'buffer_before_minutes' => $data->bufferBeforeMinutes,
                'buffer_after_minutes' => $data->bufferAfterMinutes,
                'capacity' => $data->capacity,
                'price_cents' => $data->priceCents,
                'currency' => $data->currency,
                'deposit_type' => DepositType::from($data->depositType),
                'deposit_value' => $data->depositValue,
                'cancellation_window_hours' => $data->cancellationWindowHours,
                'cancellation_policy' => $data->cancellationPolicy,
                'is_active' => $data->isActive,
            ]);

            $service->locations()->syncWithPivotValues($locationIds, ['tenant_id' => tenant('id')]);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::SERVICE_CREATED,
                    resource: 'services',
                    resourceId: $service->id,
                    metadata: ['name' => $service->name, 'slug' => $service->slug]
                )
            );

            return $service;
        });
    }

    /**
     * @param  array<int, string>  $locationIds
     * @return array<int, string>
     */
    public function resolveLocationIds(array $locationIds): array
    {
        $ids = array_values(array_unique($locationIds));

        if ($ids === []) {
            return [];
        }

        $count = Location::where('tenant_id', tenant('id'))->whereIn('id', $ids)->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'locationIds' => __('One or more locations do not belong to this business.'),
            ]);
        }

        return $ids;
    }

    public function resolveCategoryId(?string $categoryId): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        $exists = ServiceCategory::where('tenant_id', tenant('id'))
            ->where('id', $categoryId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'categoryId' => __('The selected category does not belong to this business.'),
            ]);
        }

        return $categoryId;
    }
}

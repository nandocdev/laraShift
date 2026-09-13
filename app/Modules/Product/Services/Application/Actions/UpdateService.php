<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\Actions;

use App\Modules\Product\Services\Application\DTO\ServiceData;
use App\Modules\Product\Services\Domain\Enums\DepositType;
use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class UpdateService
{
    public function __construct(private CreateService $createService) {}

    public function execute(Service $service, ServiceData $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $categoryId = $this->createService->resolveCategoryId($data->categoryId);
            $locationIds = $this->createService->resolveLocationIds($data->locationIds);

            $service->update([
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
                    action: AuditAction::SERVICE_UPDATED,
                    resource: 'services',
                    resourceId: $service->id,
                    metadata: ['name' => $service->name, 'slug' => $service->slug]
                )
            );

            return $service->refresh();
        });
    }
}

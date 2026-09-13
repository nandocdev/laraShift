<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\Actions;

use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class DeleteService
{
    public function execute(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service->delete();

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::SERVICE_DELETED,
                    resource: 'services',
                    resourceId: $service->id,
                    metadata: ['name' => $service->name, 'slug' => $service->slug]
                )
            );
        });
    }

    public function restore(Service $service): Service
    {
        $service->restore();

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::SERVICE_UPDATED,
                resource: 'services',
                resourceId: $service->id,
                metadata: ['restored' => true, 'name' => $service->name]
            )
        );

        return $service->refresh();
    }
}

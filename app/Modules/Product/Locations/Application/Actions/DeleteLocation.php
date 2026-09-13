<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Application\Actions;

use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class DeleteLocation
{
    public function execute(Location $location): void
    {
        DB::transaction(function () use ($location) {
            $location->delete();

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::LOCATION_DELETED,
                    resource: 'locations',
                    resourceId: $location->id,
                    metadata: ['name' => $location->name, 'slug' => $location->slug]
                )
            );
        });
    }

    public function restore(Location $location): Location
    {
        $location->restore();

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::LOCATION_UPDATED,
                resource: 'locations',
                resourceId: $location->id,
                metadata: ['restored' => true, 'name' => $location->name]
            )
        );

        return $location->refresh();
    }
}

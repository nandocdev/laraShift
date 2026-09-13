<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Application\Actions;

use App\Modules\Product\Resources\Domain\Models\Resource;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class DeleteResource
{
    public function execute(Resource $resource): void
    {
        DB::transaction(function () use ($resource) {
            $resource->delete();

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::RESOURCE_DELETED,
                    resource: 'resources',
                    resourceId: $resource->id,
                    metadata: ['name' => $resource->name, 'slug' => $resource->slug]
                )
            );
        });
    }

    public function restore(Resource $resource): Resource
    {
        $resource->restore();

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::RESOURCE_UPDATED,
                resource: 'resources',
                resourceId: $resource->id,
                metadata: ['restored' => true, 'name' => $resource->name]
            )
        );

        return $resource->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\Actions;

use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Product\Services\Domain\Models\ServiceCategory;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCategory
{
    public function execute(ServiceCategory $category): void
    {
        DB::transaction(function () use ($category) {
            // Soft deletes never fire FK actions, so detach explicitly.
            // The FK stays SET NULL as a hard-delete safeguard.
            Service::where('tenant_id', tenant('id'))
                ->where('category_id', $category->id)
                ->update(['category_id' => null]);

            $category->delete();

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::CATEGORY_DELETED,
                    resource: 'service_categories',
                    resourceId: $category->id,
                    metadata: ['name' => $category->name, 'slug' => $category->slug]
                )
            );
        });
    }

    public function restore(ServiceCategory $category): ServiceCategory
    {
        $category->restore();

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::CATEGORY_UPDATED,
                resource: 'service_categories',
                resourceId: $category->id,
                metadata: ['restored' => true, 'name' => $category->name]
            )
        );

        return $category->refresh();
    }
}

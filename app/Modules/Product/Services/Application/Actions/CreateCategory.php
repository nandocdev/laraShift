<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\Actions;

use App\Modules\Product\Services\Application\DTO\CategoryData;
use App\Modules\Product\Services\Domain\Models\ServiceCategory;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Facades\DB;

final readonly class CreateCategory
{
    public function execute(CategoryData $data): ServiceCategory
    {
        return DB::transaction(function () use ($data) {
            $category = ServiceCategory::create([
                'tenant_id' => tenant('id'),
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'color' => $data->color,
                'sort_order' => $data->sortOrder,
                'is_active' => $data->isActive,
            ]);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::CATEGORY_CREATED,
                    resource: 'service_categories',
                    resourceId: $category->id,
                    metadata: ['name' => $category->name, 'slug' => $category->slug]
                )
            );

            return $category;
        });
    }
}

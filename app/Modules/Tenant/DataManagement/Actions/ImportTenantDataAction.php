<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Actions;

use App\Modules\Tenant\DataManagement\DTOs\ImportData;
use App\Modules\Tenant\DataManagement\Jobs\ProcessImportJob;
use App\Modules\Tenant\DataManagement\Models\DataImport;
use Illuminate\Support\Str;

final readonly class ImportTenantDataAction
{
    public function execute(string $userId, ImportData $data): DataImport
    {
        $import = DataImport::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'user_id' => $userId,
            'file_path' => '',
            'type' => $data->type,
            'status' => 'pending',
        ]);

        ProcessImportJob::dispatch(
            tenantId: tenant('id'),
            importId: $import->id,
            records: $data->records,
            type: $data->type,
            overwrite: $data->overwrite,
        );

        return $import;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DataImport extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_data_imports';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'file_path',
        'type',
        'status',
        'summary',
        'errors',
    ];

    protected $casts = [
        'summary' => 'array',
        'errors' => 'array',
    ];
}

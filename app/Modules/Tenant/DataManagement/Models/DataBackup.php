<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Models;

use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DataBackup extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_data_backups';

    protected $fillable = [
        'id',
        'tenant_id',
        'file_path',
        'size_bytes',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}

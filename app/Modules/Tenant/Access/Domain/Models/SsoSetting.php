<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SsoSetting extends Model
{
    use HasUuids;

    protected $table = 'tenant_sso_settings';

    protected $fillable = [
        'id',
        'idp_entity_id',
        'idp_sso_url',
        'idp_x509_cert',
        'enforced_domains',
        'is_forced',
        'is_tested',
    ];

    protected $casts = [
        'enforced_domains' => 'array',
        'is_forced' => 'boolean',
        'is_tested' => 'boolean',
    ];
}

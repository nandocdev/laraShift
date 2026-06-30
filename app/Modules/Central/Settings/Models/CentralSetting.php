<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class CentralSetting extends Model
{
    protected $table = 'central_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type'];
}

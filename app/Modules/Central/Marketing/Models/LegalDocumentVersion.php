<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocumentVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'legal_document_versions';

    protected $fillable = [
        'id',
        'legal_document_id',
        'version',
        'content',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }
}

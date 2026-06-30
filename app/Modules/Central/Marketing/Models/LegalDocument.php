<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    use HasUuids;

    protected $table = 'legal_documents';

    protected $fillable = [
        'id',
        'type',
        'title',
        'content',
        'version',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class, 'legal_document_id');
    }
}

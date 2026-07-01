<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Actions;

use App\Modules\Central\Marketing\Models\Legaldocument;

final readonly class PublishLegalDocumentAction
{
    public function execute(string $documentId): Legaldocument
    {
        Legaldocument::where('type', fn ($q) => $q->select('type')->from('legal_documents')->where('id', $documentId))
            ->where('is_published', true)
            ->update(['is_published' => false]);

        $doc = Legaldocument::findOrFail($documentId);
        $doc->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        activity('legal')
            ->performedOn($doc)
            ->withProperties(['version' => $doc->version])
            ->log('legal_document_published');

        return $doc->fresh();
    }
}

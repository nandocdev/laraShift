<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Actions;

use App\Modules\Central\Marketing\Models\LegalDocument;

final readonly class PublishLegalDocumentAction
{
    public function execute(string $documentId): LegalDocument
    {
        $doc = LegalDocument::findOrFail($documentId);

        LegalDocument::where('type', $doc->type)
            ->where('is_published', true)
            ->update(['is_published' => false]);

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

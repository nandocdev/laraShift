<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Actions;

use App\Modules\Central\Marketing\Models\LegalDocument;
use App\Modules\Central\Marketing\Models\LegalDocumentVersion;
use Illuminate\Support\Str;

final readonly class UpsertLegalDocumentAction
{
    public function execute(string $type, string $title, string $content, bool $publish = false): LegalDocument
    {
        $existing = LegalDocument::where('type', $type)
            ->orderByDesc('version')
            ->first();

        $newVersion = $existing ? $existing->version + 1 : 1;

        $doc = LegalDocument::create([
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'version' => $newVersion,
            'is_published' => $publish,
            'published_at' => $publish ? now() : null,
            'created_by' => auth('central')->id(),
        ]);

        if ($existing) {
            LegalDocumentVersion::create([
                'id' => Str::uuid()->toString(),
                'legal_document_id' => $doc->id,
                'version' => $existing->version,
                'content' => $existing->content,
                'created_by' => $existing->created_by,
                'created_at' => $existing->created_at,
            ]);
        }

        activity('legal')
            ->performedOn($doc)
            ->withProperties([
                'type' => $type,
                'version' => $newVersion,
                'published' => $publish,
            ])
            ->log('legal_document_updated');

        return $doc;
    }
}

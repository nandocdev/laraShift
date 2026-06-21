<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Shared\Contracts\TenantContract;
use App\Modules\Central\Support\Models\SupportNote;
use Illuminate\Support\Str;

final readonly class CreateSupportNoteAction
{
    public function execute(TenantContract $tenant, string $content): SupportNote
    {
        $note = SupportNote::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->getId(),
            'author_id' => auth('central')->id(),
            'content' => $content,
        ]);

        activity('support')
            ->performedOn($tenant)
            ->withProperties(['note_id' => $note->id])
            ->log('support_note_created');

        return $note;
    }
}

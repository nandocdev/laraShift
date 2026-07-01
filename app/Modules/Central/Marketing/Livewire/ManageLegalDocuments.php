<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Livewire;

use App\Modules\Central\Marketing\Actions\PublishLegalDocumentAction;
use App\Modules\Central\Marketing\Actions\UpsertLegalDocumentAction;
use App\Modules\Central\Marketing\Models\LegalDocument;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManageLegalDocuments extends Component
{
    public ?LegalDocument $editing = null;

    public string $type = 'terms';

    public string $title = '';

    public string $content = '';

    public bool $publish = false;

    public function edit(LegalDocument $doc): void
    {
        $this->editing = $doc;
        $this->type = $doc->type;
        $this->title = $doc->title;
        $this->content = $doc->content;
    }

    public function resetForm(): void
    {
        $this->reset(['editing', 'type', 'title', 'content', 'publish']);
    }

    public function save(UpsertLegalDocumentAction $action): void
    {
        $this->validate([
            'type' => 'required|in:terms,privacy,cookie_policy',
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:20',
            'publish' => 'boolean',
        ]);

        $doc = $action->execute(
            $this->type,
            $this->title,
            $this->content,
            $this->publish,
            auth('central')->id(),
        );

        $this->resetForm();
        session()->flash('status', __(':type v:version saved.', ['type' => $doc->type, 'version' => $doc->version]));
    }

    public function publish(string $id, PublishLegalDocumentAction $action): void
    {
        $action->execute($id);
        session()->flash('status', __('Document published.'));
    }

    public function render(): View
    {
        $documents = LegalDocument::with('author')
            ->orderBy('type')
            ->orderByDesc('version')
            ->get()
            ->groupBy('type');

        return view('marketing::pages.legal-documents', [
            'documents' => $documents,
            'types' => [
                'terms' => __('Terms of Service'),
                'privacy' => __('Privacy Policy'),
                'cookie_policy' => __('Cookie Policy'),
            ],
        ]);
    }
}

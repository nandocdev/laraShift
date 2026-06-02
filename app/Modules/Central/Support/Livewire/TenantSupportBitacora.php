<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\CreateSupportNoteAction;
use App\Modules\Central\Support\Models\SupportNote;
use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TenantSupportBitacora extends Component
{
    public Tenant $tenant;
    public string $newNote = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function addNote(CreateSupportNoteAction $action): void
    {
        $this->validate([
            'newNote' => 'required|string|min:5',
        ]);

        try {
            $action->execute($this->tenant, $this->newNote);
            $this->reset('newNote');
            session()->flash('status', __('Note added to bitacora.'));
        } catch (\Exception $e) {
            $this->addError('newNote', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('support::livewire.tenant-support-bitacora', [
            'notes' => SupportNote::with('author')->where('tenant_id', $this->tenant->id)->latest()->get(),
            'sessions' => SupportSession::with('operator')->where('tenant_id', $this->tenant->id)->latest()->get(),
        ]);
    }
}

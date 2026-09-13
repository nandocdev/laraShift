<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\CreateSupportNoteAction;
use App\Modules\Central\Support\Models\SupportNote;
use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TenantSupportBitacora extends Component
{
    use WithPagination;

    public string $tenantId;

    public string $newNote = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenantId = $tenant->id;
    }

    public function addNote(CreateSupportNoteAction $action): void
    {
        $this->validate([
            'newNote' => 'required|string|min:5',
        ]);

        try {
            $tenant = Tenant::findOrFail($this->tenantId);
            $action->execute($tenant, $this->newNote);
            $this->reset('newNote');
            session()->flash('status', __('Note added to bitacora.'));
        } catch (\Exception $e) {
            $this->addError('newNote', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('support::livewire.tenant-support-bitacora', [
            'notes' => SupportNote::with('author')->where('tenant_id', $this->tenantId)->latest()->paginate(10, ['*'], 'notes_page'),
            'sessions' => SupportSession::with('operator')->where('tenant_id', $this->tenantId)->latest()->paginate(10, ['*'], 'sessions_page'),
        ]);
    }
}

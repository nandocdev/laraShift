<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Livewire;

use App\Modules\Tenant\DataManagement\Actions\CreateBackupAction;
use App\Modules\Tenant\DataManagement\Models\DataBackup;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageBackups extends Component
{
    use WithPagination;

    public bool $creating = false;

    public function create(CreateBackupAction $action): void
    {
        $this->creating = true;

        try {
            $action->execute();
            session()->flash('status', __('Backup started. You will be notified when ready.'));
        } catch (\Exception $e) {
            $this->addError('create', $e->getMessage());
        } finally {
            $this->creating = false;
        }
    }

    public function getDownloadUrl(string $filePath): string
    {
        return URL::temporarySignedRoute(
            'tenant.data.download',
            now()->addHours(24),
            ['path' => $filePath],
        );
    }

    public function render(): View
    {
        return view('data-management::livewire.manage-backups', [
            'backups' => DataBackup::where('tenant_id', tenant('id'))
                ->where('expires_at', '>', now())
                ->latest()
                ->paginate(20),
        ]);
    }
}

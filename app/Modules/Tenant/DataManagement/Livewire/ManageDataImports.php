<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Livewire;

use App\Modules\Tenant\DataManagement\Actions\ImportTenantDataAction;
use App\Modules\Tenant\DataManagement\DTOs\ImportData;
use App\Modules\Tenant\DataManagement\Models\DataImport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageDataImports extends Component
{
    use WithPagination;

    public string $importJson = '';

    public string $importType = 'users';

    public bool $overwrite = false;

    public function import(ImportTenantDataAction $action): void
    {
        $this->validate([
            'importJson' => 'required|string',
            'importType' => 'required|in:users,settings',
            'overwrite' => 'boolean',
        ]);

        $records = json_decode($this->importJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('importJson', __('Invalid JSON format.'));

            return;
        }

        if (! is_array($records)) {
            $this->addError('importJson', __('JSON must be an array of records.'));

            return;
        }

        if (count($records) > 1000) {
            $this->addError('importJson', __('Maximum 1000 records per import.'));

            return;
        }

        $data = new ImportData(
            type: $this->importType,
            records: $records,
            overwrite: $this->overwrite,
        );

        $action->execute(auth()->id(), $data);

        $this->reset(['importJson']);
        session()->flash('status', __('Import queued successfully.'));
    }

    public function render(): View
    {
        return view('data-management::livewire.manage-imports', [
            'imports' => DataImport::where('tenant_id', tenant('id'))->latest()->paginate(20),
        ]);
    }
}

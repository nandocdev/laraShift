<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Interface\Livewire;

use App\Modules\Product\Locations\Application\Actions\CreateLocation;
use App\Modules\Product\Locations\Application\Actions\DeleteLocation;
use App\Modules\Product\Locations\Application\Actions\UpdateLocation;
use App\Modules\Product\Locations\Application\DTO\LocationData;
use App\Modules\Product\Locations\Domain\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageLocations extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Locked]
    public ?string $editingId = null;

    public string $name = '';

    public string $slug = '';

    public bool $autoGenerateSlug = true;

    public string $phone = '';

    public string $email = '';

    public string $timezone = '';

    public bool $isActive = true;

    /** @var array{line1: ?string, city: ?string, state: ?string, postal_code: ?string, country: ?string} */
    public array $address = [
        'line1' => null,
        'city' => null,
        'state' => null,
        'postal_code' => null,
        'country' => null,
    ];

    public bool $showTrashed = false;

    public function updatedName(string $value): void
    {
        if ($this->autoGenerateSlug) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->autoGenerateSlug = false;
        $this->slug = Str::slug($value);
    }

    public function edit(string $id): void
    {
        $location = Location::findOrFail($id);

        $this->editingId = $location->id;
        $this->name = $location->name;
        $this->slug = $location->slug;
        $this->autoGenerateSlug = false;
        $this->phone = $location->phone ?? '';
        $this->email = $location->email ?? '';
        $this->timezone = $location->timezone ?? '';
        $this->isActive = (bool) $location->is_active;
        $this->address = array_merge($this->address, $location->address ?? []);
    }

    public function cancelEdit(): void
    {
        $this->reset([
            'editingId', 'name', 'slug', 'phone', 'email',
            'timezone', 'isActive', 'address',
        ]);
        $this->autoGenerateSlug = true;
    }

    public function save(CreateLocation $create, UpdateLocation $update): void
    {
        $this->authorize('locations:manage');

        $validated = $this->validate($this->rules());

        $data = new LocationData(
            name: $validated['name'],
            slug: $validated['slug'],
            phone: $validated['phone'] ?: null,
            email: $validated['email'] ?: null,
            timezone: $validated['timezone'] ?: null,
            isActive: (bool) $validated['isActive'],
            address: $this->normalizedAddress($validated['address'] ?? []),
        );

        if ($this->editingId) {
            $update->execute(Location::findOrFail($this->editingId), $data);
            session()->flash('status', __('Location updated.'));
        } else {
            $create->execute($data);
            session()->flash('status', __('Location created.'));
        }

        $this->cancelEdit();
    }

    public function delete(string $id, DeleteLocation $action): void
    {
        $this->authorize('locations:manage');

        $action->execute(Location::findOrFail($id));

        session()->flash('status', __('Location deleted.'));
    }

    public function restore(string $id, DeleteLocation $action): void
    {
        $this->authorize('locations:manage');

        try {
            $action->restore(Location::withTrashed()->findOrFail($id));
        } catch (QueryException) {
            $this->addError('slug', __('Its slug is already in use by another location.'));

            return;
        }

        $this->resetErrorBag();

        session()->flash('status', __('Location restored.'));
    }

    public function render(): View
    {
        $query = Location::query()->latest();

        if ($this->showTrashed) {
            $query->withTrashed();
        }

        return view('locations::livewire.manage-locations', [
            'locations' => $query->paginate(10),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $unique = Rule::unique('locations', 'slug')
            ->where('tenant_id', tenant('id'))
            ->whereNull('deleted_at');

        if ($this->editingId) {
            $unique->ignore($this->editingId, 'id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', $unique],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'isActive' => ['boolean'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'size:2'],
        ];
    }

    /**
     * @param  array<string, ?string>  $address
     * @return array<string, string>|null
     */
    private function normalizedAddress(array $address): ?array
    {
        $normalized = collect($address)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        return $normalized !== [] ? $normalized : null;
    }
}

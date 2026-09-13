<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Interface\Livewire;

use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Resources\Application\Actions\CreateResource;
use App\Modules\Product\Resources\Application\Actions\DeleteResource;
use App\Modules\Product\Resources\Application\Actions\UpdateResource;
use App\Modules\Product\Resources\Application\DTO\ResourceData;
use App\Modules\Product\Resources\Domain\Enums\ResourceType;
use App\Modules\Product\Resources\Domain\Models\Resource;
use App\Modules\Product\Services\Domain\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageResources extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $showTrashed = false;

    #[Locked]
    public ?string $editingId = null;

    public string $name = '';

    public string $slug = '';

    public bool $autoGenerateSlug = true;

    public string $type = 'staff';

    public ?string $locationId = null;

    public string $description = '';

    public int $capacity = 1;

    /** @var array<int, string> */
    public array $skills = [];

    public string $rate = '';

    public string $currency = '';

    /** @var array<int, string> */
    public array $serviceIds = [];

    public bool $isActive = true;

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

    public function addSkill(): void
    {
        $this->skills[] = '';
    }

    public function removeSkill(int $index): void
    {
        unset($this->skills[$index]);
        $this->skills = array_values($this->skills);
    }

    #[Computed]
    public function locations(): mixed
    {
        return Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function services(): mixed
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function edit(string $id): void
    {
        $resource = Resource::with('services')->findOrFail($id);

        $this->editingId = $resource->id;
        $this->name = $resource->name;
        $this->slug = $resource->slug;
        $this->autoGenerateSlug = false;
        $this->type = $resource->type->value;
        $this->locationId = $resource->location_id;
        $this->description = $resource->description ?? '';
        $this->capacity = $resource->capacity;
        $this->skills = array_values($resource->skills ?? []);
        $this->rate = $resource->rate_cents !== null
            ? number_format($resource->rate_cents / 100, 2, '.', '')
            : '';
        $this->currency = $resource->currency ?? '';
        $this->serviceIds = $resource->services->pluck('id')->all();
        $this->isActive = (bool) $resource->is_active;
    }

    public function cancelEdit(): void
    {
        $this->reset([
            'editingId', 'name', 'slug', 'type', 'locationId', 'description',
            'capacity', 'skills', 'rate', 'currency', 'serviceIds', 'isActive',
        ]);
        $this->autoGenerateSlug = true;
    }

    public function save(CreateResource $create, UpdateResource $update): void
    {
        $this->authorize('resources:manage');

        if ($this->locationId === '') {
            $this->locationId = null;
        }

        $validated = $this->validate($this->rules());

        $rate = trim($validated['rate']);
        $skills = collect($validated['skills'] ?? [])
            ->map(fn ($skill) => is_string($skill) ? trim($skill) : '')
            ->reject(fn ($skill) => $skill === '')
            ->unique()
            ->values()
            ->all();

        $data = new ResourceData(
            name: $validated['name'],
            slug: $validated['slug'],
            type: $validated['type'],
            locationId: $validated['locationId'] ?? null,
            description: trim($validated['description']) !== '' ? trim($validated['description']) : null,
            capacity: (int) $validated['capacity'],
            skills: $skills !== [] ? $skills : null,
            rateCents: $rate !== '' ? (int) round((float) $rate * 100) : null,
            currency: $validated['currency'] !== '' ? strtoupper($validated['currency']) : null,
            isActive: (bool) $validated['isActive'],
            serviceIds: $validated['serviceIds'] ?? [],
        );

        try {
            if ($this->editingId) {
                $update->execute(Resource::findOrFail($this->editingId), $data);
                session()->flash('status', __('Resource updated.'));
            } else {
                $create->execute($data);
                session()->flash('status', __('Resource created.'));
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->cancelEdit();
    }

    public function delete(string $id, DeleteResource $action): void
    {
        $this->authorize('resources:manage');

        $action->execute(Resource::findOrFail($id));

        session()->flash('status', __('Resource deleted.'));
    }

    public function restore(string $id, DeleteResource $action): void
    {
        $this->authorize('resources:manage');

        try {
            $action->restore(Resource::withTrashed()->findOrFail($id));
        } catch (QueryException) {
            $this->addError('slug', __('Its slug is already in use by another resource.'));

            return;
        }

        $this->resetErrorBag();

        session()->flash('status', __('Resource restored.'));
    }

    public function render(): View
    {
        $resources = Resource::query()
            ->with(['location', 'services'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->showTrashed, fn ($query) => $query->withTrashed())
            ->latest()
            ->paginate(10);

        return view('resources::livewire.manage-resources', [
            'resources' => $resources,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $unique = Rule::unique('resources', 'slug')
            ->where('tenant_id', tenant('id'))
            ->whereNull('deleted_at');

        if ($this->editingId) {
            $unique->ignore($this->editingId, 'id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', $unique],
            'type' => ['required', 'string', Rule::in(array_column(ResourceType::cases(), 'value'))],
            'locationId' => ['nullable', 'string', Rule::exists('locations', 'id')->where('tenant_id', tenant('id'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'skills' => ['nullable', 'array', 'max:20'],
            'skills.*' => ['nullable', 'string', 'max:80'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'serviceIds' => ['nullable', 'array'],
            'serviceIds.*' => ['string', Rule::exists('services', 'id')->where('tenant_id', tenant('id'))],
            'isActive' => ['boolean'],
        ];
    }
}

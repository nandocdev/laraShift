<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Interface\Livewire;

use App\Modules\Product\Locations\Domain\Models\Location;
use App\Modules\Product\Services\Application\Actions\CreateCategory;
use App\Modules\Product\Services\Application\Actions\CreateService;
use App\Modules\Product\Services\Application\Actions\DeleteCategory;
use App\Modules\Product\Services\Application\Actions\DeleteService;
use App\Modules\Product\Services\Application\Actions\UpdateCategory;
use App\Modules\Product\Services\Application\Actions\UpdateService;
use App\Modules\Product\Services\Application\DTO\CategoryData;
use App\Modules\Product\Services\Application\DTO\ServiceData;
use App\Modules\Product\Services\Domain\Enums\DepositType;
use App\Modules\Product\Services\Domain\Models\Service;
use App\Modules\Product\Services\Domain\Models\ServiceCategory;
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
class ManageServices extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $activeTab = 'services';

    public string $search = '';

    public string $categoryFilter = '';

    public bool $showTrashed = false;

    // Service form state
    #[Locked]
    public ?string $editingId = null;

    public string $name = '';

    public string $slug = '';

    public bool $autoGenerateSlug = true;

    public string $categoryId = '';

    public string $description = '';

    public int $durationMinutes = 60;

    public int $bufferBeforeMinutes = 0;

    public int $bufferAfterMinutes = 0;

    public int $capacity = 1;

    public string $price = '0';

    public string $currency = '';

    public string $depositType = 'none';

    public string $depositValue = '';

    public string $cancellationWindowHours = '';

    /** @var array<int, array{min_hours: string, refund_percent: string}> */
    public array $cancellationRules = [];

    /** @var array<int, string> */
    public array $locationIds = [];

    public bool $isActive = true;

    // Category form state
    #[Locked]
    public ?string $editingCategoryId = null;

    public string $categoryName = '';

    public string $categorySlug = '';

    public bool $categoryAutoSlug = true;

    public string $categoryDescription = '';

    public string $categoryColor = '';

    public int $categorySortOrder = 0;

    public bool $categoryIsActive = true;

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

    public function updatedCategoryName(string $value): void
    {
        if ($this->categoryAutoSlug) {
            $this->categorySlug = Str::slug($value);
        }
    }

    public function updatedCategorySlug(string $value): void
    {
        $this->categoryAutoSlug = false;
        $this->categorySlug = Str::slug($value);
    }

    public function addCancellationRule(): void
    {
        $this->cancellationRules[] = ['min_hours' => '', 'refund_percent' => ''];
    }

    public function removeCancellationRule(int $index): void
    {
        unset($this->cancellationRules[$index]);
        $this->cancellationRules = array_values($this->cancellationRules);
    }

    #[Computed]
    public function categories(): mixed
    {
        return ServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function locations(): mixed
    {
        return Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function editService(string $id): void
    {
        $service = Service::with('locations')->findOrFail($id);

        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->slug = $service->slug;
        $this->autoGenerateSlug = false;
        $this->categoryId = $service->category_id ?? '';
        $this->description = $service->description ?? '';
        $this->durationMinutes = $service->duration_minutes;
        $this->bufferBeforeMinutes = $service->buffer_before_minutes;
        $this->bufferAfterMinutes = $service->buffer_after_minutes;
        $this->capacity = $service->capacity;
        $this->price = number_format($service->price_cents / 100, 2, '.', '');
        $this->currency = $service->currency ?? '';
        $this->depositType = $service->deposit_type->value;
        $this->depositValue = $service->deposit_type === DepositType::Percentage
            ? (string) $service->deposit_value
            : ($service->deposit_value !== null ? number_format($service->deposit_value / 100, 2, '.', '') : '');
        $this->cancellationWindowHours = $service->cancellation_window_hours !== null
            ? (string) $service->cancellation_window_hours
            : '';
        $this->cancellationRules = collect($service->cancellation_policy ?? [])
            ->map(fn ($rule) => [
                'min_hours' => (string) ($rule['min_hours'] ?? ''),
                'refund_percent' => (string) ($rule['refund_percent'] ?? ''),
            ])->all();
        $this->locationIds = $service->locations->pluck('id')->all();
        $this->isActive = (bool) $service->is_active;
    }

    public function cancelServiceEdit(): void
    {
        $this->reset([
            'editingId', 'name', 'slug', 'categoryId', 'description',
            'durationMinutes', 'bufferBeforeMinutes', 'bufferAfterMinutes',
            'capacity', 'price', 'currency', 'depositType', 'depositValue',
            'cancellationWindowHours', 'cancellationRules', 'locationIds', 'isActive',
        ]);
        $this->autoGenerateSlug = true;
    }

    public function saveService(CreateService $create, UpdateService $update): void
    {
        $this->authorize('services:manage');

        $validated = $this->validate($this->serviceRules());
        $deposit = $this->resolveDeposit($validated);
        $cancellationPolicy = $this->resolveCancellationPolicy($validated);

        $data = new ServiceData(
            name: $validated['name'],
            slug: $validated['slug'],
            durationMinutes: (int) $validated['durationMinutes'],
            capacity: (int) $validated['capacity'],
            priceCents: (int) round((float) $validated['price'] * 100),
            categoryId: $validated['categoryId'] !== '' ? $validated['categoryId'] : null,
            description: trim($validated['description']) !== '' ? trim($validated['description']) : null,
            bufferBeforeMinutes: (int) $validated['bufferBeforeMinutes'],
            bufferAfterMinutes: (int) $validated['bufferAfterMinutes'],
            currency: $validated['currency'] !== '' ? strtoupper($validated['currency']) : null,
            depositType: $validated['depositType'],
            depositValue: $deposit,
            cancellationWindowHours: $validated['cancellationWindowHours'] !== '' ? (int) $validated['cancellationWindowHours'] : null,
            cancellationPolicy: $cancellationPolicy,
            isActive: (bool) $validated['isActive'],
            locationIds: $validated['locationIds'] ?? [],
        );

        try {
            if ($this->editingId) {
                $update->execute(Service::findOrFail($this->editingId), $data);
                session()->flash('status', __('Service updated.'));
            } else {
                $create->execute($data);
                session()->flash('status', __('Service created.'));
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->cancelServiceEdit();
    }

    public function deleteService(string $id, DeleteService $action): void
    {
        $this->authorize('services:manage');

        $action->execute(Service::findOrFail($id));

        session()->flash('status', __('Service deleted.'));
    }

    public function restoreService(string $id, DeleteService $action): void
    {
        $this->authorize('services:manage');

        try {
            $action->restore(Service::withTrashed()->findOrFail($id));
        } catch (QueryException) {
            $this->addError('slug', __('Its slug is already in use by another service.'));

            return;
        }

        $this->resetErrorBag();

        session()->flash('status', __('Service restored.'));
    }

    public function editCategory(string $id): void
    {
        $category = ServiceCategory::findOrFail($id);

        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categorySlug = $category->slug;
        $this->categoryAutoSlug = false;
        $this->categoryDescription = $category->description ?? '';
        $this->categoryColor = $category->color ?? '';
        $this->categorySortOrder = $category->sort_order;
        $this->categoryIsActive = (bool) $category->is_active;
    }

    public function cancelCategoryEdit(): void
    {
        $this->reset([
            'editingCategoryId', 'categoryName', 'categorySlug', 'categoryDescription',
            'categoryColor', 'categorySortOrder', 'categoryIsActive',
        ]);
        $this->categoryAutoSlug = true;
    }

    public function saveCategory(CreateCategory $create, UpdateCategory $update): void
    {
        $this->authorize('services:manage');

        $validated = $this->validate($this->categoryRules());

        $data = new CategoryData(
            name: $validated['categoryName'],
            slug: $validated['categorySlug'],
            description: trim($validated['categoryDescription']) !== '' ? trim($validated['categoryDescription']) : null,
            color: $validated['categoryColor'] !== '' ? strtolower($validated['categoryColor']) : null,
            sortOrder: (int) $validated['categorySortOrder'],
            isActive: (bool) $validated['categoryIsActive'],
        );

        if ($this->editingCategoryId) {
            $update->execute(ServiceCategory::findOrFail($this->editingCategoryId), $data);
            session()->flash('status', __('Category updated.'));
        } else {
            $create->execute($data);
            session()->flash('status', __('Category created.'));
        }

        $this->cancelCategoryEdit();
        unset($this->categories);
    }

    public function deleteCategory(string $id, DeleteCategory $action): void
    {
        $this->authorize('services:manage');

        $action->execute(ServiceCategory::findOrFail($id));

        unset($this->categories);

        session()->flash('status', __('Category deleted. Its services are now uncategorized.'));
    }

    public function render(): View
    {
        $services = Service::query()
            ->with(['category', 'locations'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->showTrashed, fn ($query) => $query->withTrashed())
            ->latest()
            ->paginate(10);

        $categories = ServiceCategory::query()
            ->withCount('services')
            ->when($this->showTrashed, fn ($query) => $query->withTrashed())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10, ['*'], 'categories_page');

        return view('services::livewire.manage-services', [
            'services' => $services,
            'categoriesList' => $categories,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceRules(): array
    {
        $unique = Rule::unique('services', 'slug')
            ->where('tenant_id', tenant('id'))
            ->whereNull('deleted_at');

        if ($this->editingId) {
            $unique->ignore($this->editingId, 'id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', $unique],
            'categoryId' => ['nullable', 'string', Rule::exists('service_categories', 'id')->where('tenant_id', tenant('id'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'durationMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'bufferBeforeMinutes' => ['required', 'integer', 'min:0', 'max:480'],
            'bufferAfterMinutes' => ['required', 'integer', 'min:0', 'max:480'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'depositType' => ['required', 'string', Rule::in(['none', 'percentage', 'fixed'])],
            'depositValue' => ['nullable', 'string', 'max:20'],
            'cancellationWindowHours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancellationRules' => ['nullable', 'array', 'max:10'],
            'cancellationRules.*.min_hours' => ['required_with:cancellationRules', 'integer', 'min:0', 'max:8760'],
            'cancellationRules.*.refund_percent' => ['required_with:cancellationRules', 'integer', 'min:0', 'max:100'],
            'locationIds' => ['nullable', 'array'],
            'locationIds.*' => ['string', Rule::exists('locations', 'id')->where('tenant_id', tenant('id'))],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRules(): array
    {
        $unique = Rule::unique('service_categories', 'slug')
            ->where('tenant_id', tenant('id'))
            ->whereNull('deleted_at');

        if ($this->editingCategoryId) {
            $unique->ignore($this->editingCategoryId, 'id');
        }

        return [
            'categoryName' => ['required', 'string', 'max:255'],
            'categorySlug' => ['required', 'string', 'max:100', 'alpha_dash', $unique],
            'categoryDescription' => ['nullable', 'string', 'max:2000'],
            'categoryColor' => ['nullable', 'hex_color'],
            'categorySortOrder' => ['required', 'integer', 'min:0', 'max:1000'],
            'categoryIsActive' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveDeposit(array $validated): ?int
    {
        $type = $validated['depositType'];
        $raw = trim((string) ($validated['depositValue'] ?? ''));

        if ($type === DepositType::Percentage->value) {
            if ($raw === '' || ! ctype_digit($raw) || (int) $raw < 0 || (int) $raw > 100) {
                throw ValidationException::withMessages([
                    'depositValue' => __('Enter a percentage between 0 and 100.'),
                ]);
            }

            return (int) $raw;
        }

        if ($type === DepositType::Fixed->value) {
            if ($raw === '' || ! is_numeric($raw) || (float) $raw < 0) {
                throw ValidationException::withMessages([
                    'depositValue' => __('Enter a fixed deposit amount of 0 or more.'),
                ]);
            }

            return (int) round((float) $raw * 100);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{min_hours: int, refund_percent: int}>|null
     */
    private function resolveCancellationPolicy(array $validated): ?array
    {
        $rules = collect($validated['cancellationRules'] ?? [])
            ->map(fn ($rule) => [
                'min_hours' => (int) $rule['min_hours'],
                'refund_percent' => (int) $rule['refund_percent'],
            ])
            ->sortByDesc('min_hours')
            ->values()
            ->all();

        return $rules !== [] ? $rules : null;
    }
}

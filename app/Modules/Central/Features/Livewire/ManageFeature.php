<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Livewire;

use App\Modules\Central\Features\Models\Feature;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManageFeature extends Component
{
    public ?Feature $feature = null;

    public bool $isEditing = false;

    public string $key = '';

    public string $name = '';

    public string $description = '';

    public string $module = '';

    public bool $is_active = true;

    public array $targeting = [
        'regions' => [],
        'staff_min' => null,
        'staff_max' => null,
        'min_tenancy_days' => null,
    ];

    public string $regionInput = '';

    public function mount(?Feature $feature = null): void
    {
        if ($feature && $feature->exists) {
            $this->feature = $feature;
            $this->isEditing = true;
            $this->key = $feature->key;
            $this->name = $feature->name;
            $this->description = $feature->description ?? '';
            $this->module = $feature->module ?? '';
            $this->is_active = $feature->is_active;
            $this->targeting = $feature->targeting ?? $this->targeting;
        }
    }

    public function updatedKey($value): void
    {
        $this->key = Str::lower(Str::slug($value, '.'));
    }

    public function addRegion(): void
    {
        $region = strtoupper(trim($this->regionInput));
        if ($region && ! in_array($region, $this->targeting['regions'])) {
            $this->targeting['regions'][] = $region;
        }
        $this->regionInput = '';
    }

    public function removeRegion(string $region): void
    {
        $this->targeting['regions'] = array_values(array_filter(
            $this->targeting['regions'],
            fn ($r) => $r !== $region
        ));
    }

    public function save(): void
    {
        $this->validate([
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+\.[a-z0-9_]+$/',
                'unique:features,key,'.($this->feature->id ?? 'NULL').',id',
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'targeting.regions' => 'nullable|array',
            'targeting.staff_min' => 'nullable|integer|min:0',
            'targeting.staff_max' => 'nullable|integer|min:0',
            'targeting.min_tenancy_days' => 'nullable|integer|min:0',
        ], [
            'key.regex' => __('The key must follow the format module.action (e.g. auth.mfa_enforce)'),
        ]);

        $attributes = [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'is_active' => $this->is_active,
            'targeting' => $this->targeting,
        ];

        if ($this->isEditing) {
            $changes = [];
            foreach ($attributes as $field => $value) {
                if ($this->feature->getAttribute($field) != $value) {
                    $changes[$field] = ['from' => $this->feature->getAttribute($field), 'to' => $value];
                }
            }

            $this->feature->update($attributes);

            if (! empty($changes)) {
                activity('features')
                    ->performedOn($this->feature)
                    ->withProperties(['changes' => $changes, 'actor' => auth('central')->id()])
                    ->log('feature_updated');
            }

            session()->flash('status', __('Feature updated.'));
        } else {
            $attributes['id'] = Str::uuid()->toString();
            $feature = Feature::create($attributes);

            activity('features')
                ->performedOn($feature)
                ->withProperties(['actor' => auth('central')->id()])
                ->log('feature_created');

            session()->flash('status', __('Feature created.'));
        }

        $this->redirect(route('central.features.index'), navigate: true);
    }

    public function delete(): void
    {
        if (! $this->feature) {
            return;
        }

        activity('features')
            ->performedOn($this->feature)
            ->withProperties(['actor' => auth('central')->id()])
            ->log('feature_deleted');

        $this->feature->delete();

        session()->flash('status', __('Feature retired. Historical data remains valid.'));
        $this->redirect(route('central.features.index'), navigate: true);
    }

    public function render(): View
    {
        return view('features::pages.manage-feature');
    }
}

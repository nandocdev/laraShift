<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Livewire;

use App\Modules\Central\Features\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Str;

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
        }
    }

    public function save(): void
    {
        $this->validate([
            'key' => 'required|string|max:100|unique:features,key,' . ($this->feature->id ?? 'NULL') . ',id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $attributes = [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditing) {
            $this->feature->update($attributes);
            session()->flash('status', __('Feature updated.'));
        } else {
            $attributes['id'] = Str::uuid()->toString();
            Feature::create($attributes);
            session()->flash('status', __('Feature created.'));
        }

        $this->redirect(route('central.features.index'), navigate: true);
    }

    public function delete(): void
    {
        if (! $this->feature) return;

        // Note: In production we should check if used in overrides/plans
        $this->feature->delete();
        session()->flash('status', __('Feature deleted.'));
        $this->redirect(route('central.features.index'), navigate: true);
    }

    public function render(): View
    {
        return view('features::pages.manage-feature');
    }
}

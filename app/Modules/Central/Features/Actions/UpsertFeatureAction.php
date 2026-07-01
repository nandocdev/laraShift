<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Actions;

use App\Modules\Central\Features\DTOs\FeatureData;
use App\Modules\Central\Features\Models\Feature;
use Illuminate\Support\Str;

final readonly class UpsertFeatureAction
{
    public function execute(FeatureData $data, ?Feature $feature = null): Feature
    {
        $attributes = [
            'key' => $data->key,
            'name' => $data->name,
            'description' => $data->description,
            'module' => $data->module,
            'is_active' => $data->is_active,
            'targeting' => $data->targeting,
        ];

        if ($feature && $feature->exists) {
            $changes = [];
            foreach ($attributes as $field => $value) {
                if ($feature->getAttribute($field) != $value) {
                    $changes[$field] = ['from' => $feature->getAttribute($field), 'to' => $value];
                }
            }

            $feature->update($attributes);

            if (! empty($changes)) {
                activity('features')
                    ->performedOn($feature)
                    ->withProperties(['changes' => $changes, 'actor' => auth('central')->id()])
                    ->log('feature_updated');
            }

            return $feature;
        }

        $attributes['id'] = Str::uuid()->toString();
        $feature = Feature::create($attributes);

        activity('features')
            ->performedOn($feature)
            ->withProperties(['actor' => auth('central')->id()])
            ->log('feature_created');

        return $feature;
    }
}

<?php

declare(strict_types=1);

use App\Modules\Platform\UI\Navigation\SidebarBuilder;

test('builds empty sidebar', function () {
    $builder = new SidebarBuilder;
    expect($builder->toArray())->toBe([]);
});

test('adds groups', function () {
    $builder = new SidebarBuilder;
    $builder->addGroup('Main', [['label' => 'Dashboard', 'icon' => 'home', 'route' => 'home']]);
    $builder->addGroup('Settings', []);

    expect($builder->toArray())->toHaveCount(2);
});

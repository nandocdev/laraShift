<?php

declare(strict_types=1);

namespace App\Modules\Platform\UI\Navigation;

use Illuminate\Support\Collection;

class SidebarBuilder
{
    /** @var Collection<int, array> */
    private Collection $groups;

    public function __construct()
    {
        $this->groups = collect();
    }

    /**
     * Adds a navigation group with items.
     *
     * @param  string  $heading  Translation key for the group heading.
     * @param  array<int, array{label: string, icon: string, route: string, current?: string}>  $items
     */
    public function addGroup(string $heading, array $items): static
    {
        $this->groups->push([
            'heading' => $heading,
            'items' => $items,
        ]);

        return $this;
    }

    /**
     * Renders the sidebar navigation as HTML.
     */
    public function render(): string
    {
        $html = '';

        foreach ($this->groups as $group) {
            $html .= '<flux:sidebar.group heading="'.__($group['heading']).'" class="grid">';

            foreach ($group['items'] as $item) {
                $current = isset($item['current']) ? ':current="request()->routeIs(\''.$item['current'].'\')"' : '';
                $html .= '<flux:sidebar.item icon="'.$item['icon'].'" href="'.route($item['route']).'" '.$current.' wire:navigate>';
                $html .= __($item['label']);
                $html .= '</flux:sidebar.item>';
            }

            $html .= '</flux:sidebar.group>';
        }

        return $html;
    }

    /**
     * Returns the groups data for custom rendering in Blade.
     *
     * @return array<int, array{heading: string, items: array}>
     */
    public function toArray(): array
    {
        return $this->groups->toArray();
    }
}

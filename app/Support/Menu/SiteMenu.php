<?php

namespace App\Support\Menu;

use App\Models\MenuItem;
use App\Shop\ShopSettings;
use Illuminate\Support\Collection;

/**
 * Das Hauptmenü der Website als Baum, nur mit sichtbaren Punkten.
 * Ist der Shop eingeschaltet, aber nicht im Menü, wird er hinten angehängt.
 */
class SiteMenu
{
    /** @return list<array{label: string, url: string, new_tab: bool, active: bool, children: list<array>}> */
    public static function tree(?string $currentPath = null): array
    {
        $current = '/'.ltrim($currentPath ?? request()->path(), '/');
        $items = MenuItem::with('page')->orderBy('position')->get();
        $byParent = $items->groupBy(fn ($i) => $i->parent_id ?? 0);

        $build = function (int $parent, int $depth) use (&$build, $byParent, $current): array {
            $out = [];
            foreach ($byParent->get($parent, new Collection) as $item) {
                if (! $item->isLive()) {
                    continue;
                }
                $url = $item->href();
                $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
                $children = $depth < 1 ? $build($item->id, $depth + 1) : [];
                $active = $item->type !== 'link' && ($path === $current || ($path !== '/' && str_starts_with($current, $path.'/')));
                $out[] = [
                    'label' => $item->title(),
                    'url' => $url,
                    'new_tab' => $item->new_tab,
                    'active' => $active || collect($children)->contains('active', true),
                    'children' => $children,
                ];
            }

            return $out;
        };

        $tree = $build(0, 0);
        if (ShopSettings::enabled() && ! $items->contains('type', 'shop')) {
            $tree[] = ['label' => 'Shop', 'url' => route('shop.index'), 'new_tab' => false,
                'active' => str_starts_with($current, '/shop'), 'children' => []];
        }

        return $tree;
    }
}

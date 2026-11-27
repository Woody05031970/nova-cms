<?php

namespace App\Support\Menu;

use App\Models\MenuItem;
use App\Models\Page;
use App\Shop\ShopSettings;
use App\Support\I18n\Languages;
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
        // Mehrsprachigkeit: Menüpunkte zeigen auf die Übersetzung, wenn es eine gibt
        $locale = Languages::current();
        $translations = $locale !== Languages::MAIN && Languages::on()
            ? Page::published()->where('locale', $locale)->whereNotNull('translation_of')->get()->keyBy('translation_of') : collect();
        $guest = ! auth()->user()?->canSeeMembersArea();

        $build = function (int $parent, int $depth) use (&$build, $byParent, $current, $translations, $guest): array {
            $out = [];
            foreach ($byParent->get($parent, new Collection) as $item) {
                if (! $item->isLive() || ($guest && $item->type === 'page' && $item->page?->isMembersOnly())) {
                    continue;
                }
                $translation = $item->type === 'page' ? $translations->get($item->page_id) : null;
                $url = $translation ? $translation->url() : $item->href();
                $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
                $children = $depth < 1 ? $build($item->id, $depth + 1) : [];
                $active = $item->type !== 'link' && ($path === $current || ($path !== '/' && str_starts_with($current, $path.'/')));
                $out[] = [
                    'label' => $translation ? $translation->title : $item->title(),
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

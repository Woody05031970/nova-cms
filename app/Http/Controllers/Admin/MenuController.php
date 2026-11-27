<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Page;
use App\Shop\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Hauptmenü der Website: Punkte anordnen, verschachteln, umbenennen und ergänzen. */
class MenuController extends Controller
{
    public function index(): View
    {
        $items = MenuItem::with('page')->orderBy('position')->get();
        $byParent = $items->groupBy(fn ($i) => $i->parent_id ?? 0);
        $map = fn (MenuItem $i) => [
            'key' => 'm'.$i->id,
            'id' => $i->id,
            'type' => $i->type,
            'page_id' => $i->page_id,
            'label' => (string) $i->label,
            'url' => (string) $i->url,
            'new_tab' => $i->new_tab,
            'is_visible' => $i->is_visible,
        ];
        $tree = $byParent->get(0, collect())->map(fn ($i) => $map($i) + [
            'children' => $byParent->get($i->id, collect())->map(fn ($c) => $map($c) + ['children' => []])->values(),
        ])->values();

        $pages = Page::orderByDesc('is_home')->orderBy('title')->get(['id', 'title', 'slug', 'is_home', 'status'])
            ->map(fn (Page $p) => ['id' => $p->id, 'title' => $p->title, 'url' => $p->is_home ? '/' : '/'.$p->slug, 'published' => $p->isPublished()]);

        return view('admin.menu', [
            'tree' => $tree,
            'pages' => $pages,
            'shopEnabled' => ShopSettings::enabled(),
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['tree' => ['required', 'json']]);
        $tree = json_decode($request->input('tree'), true);
        if (! is_array($tree)) {
            throw ValidationException::withMessages(['tree' => 'Das Menü konnte nicht gelesen werden.']);
        }

        DB::transaction(function () use ($tree) {
            $keep = [];
            $pageIds = [];
            foreach (array_values($tree) as $pos => $node) {
                $item = $this->saveNode($node, null, $pos, $keep, $pageIds);
                foreach (array_values(array_slice((array) ($node['children'] ?? []), 0, 50)) as $cpos => $child) {
                    $this->saveNode($child, $item->id, $cpos, $keep, $pageIds); // nur zwei Ebenen
                }
            }
            MenuItem::whereNotIn('id', $keep ?: [0])->delete();

            // "Im Menü anzeigen" bei den Seiten nachziehen (ohne erneutes Auslösen der Synchronisierung)
            Page::whereIn('id', $pageIds ?: [0])->update(['show_in_menu' => true]);
            Page::whereNotIn('id', $pageIds ?: [0])->update(['show_in_menu' => false]);
        });

        $message = 'Menü gespeichert. Die Website zeigt es ab sofort so an.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : redirect()->route('admin.menu')->with('status', $message);
    }

    /** @param list<int> $keep @param list<int> $pageIds */
    private function saveNode(mixed $node, ?int $parentId, int $position, array &$keep, array &$pageIds): MenuItem
    {
        $node = is_array($node) ? $node : [];
        $type = in_array($node['type'] ?? null, MenuItem::TYPES, true) ? $node['type'] : 'link';
        $pageId = $type === 'page' ? (int) ($node['page_id'] ?? 0) : null;
        if ($type === 'page' && ! Page::whereKey($pageId)->exists()) {
            throw ValidationException::withMessages(['tree' => 'Eine Seite im Menü gibt es nicht mehr. Bitte laden Sie die Seite neu.']);
        }
        $url = $type === 'link' ? $this->url((string) ($node['url'] ?? '')) : null;
        if ($type === 'link' && $url === null) {
            throw ValidationException::withMessages(['tree' => 'Der Link „'.mb_substr((string) ($node['label'] ?? ''), 0, 40).'“ hat keine gültige Adresse. Erlaubt sind z. B. /kontakt oder https://…']);
        }

        $item = isset($node['id']) ? MenuItem::find((int) $node['id']) : null;
        $item ??= new MenuItem;
        $item->fill([
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $type,
            'page_id' => $pageId,
            'label' => mb_substr(trim(strip_tags((string) ($node['label'] ?? ''))), 0, 80) ?: null,
            'url' => $url,
            'new_tab' => (bool) ($node['new_tab'] ?? false),
            'is_visible' => (bool) ($node['is_visible'] ?? true),
        ])->save();

        $keep[] = $item->id;
        if ($pageId) {
            $pageIds[] = $pageId;
        }

        return $item;
    }

    private function url(string $v): ?string
    {
        $v = trim($v);
        if ($v === '' || preg_match('/[\s<>"]/', $v)) {
            return null;
        }
        if (str_starts_with($v, '/') && ! str_starts_with($v, '//')) {
            return mb_substr($v, 0, 500);
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $v)) {
            return mb_substr($v, 0, 500);
        }
        if (str_starts_with($v, '#')) {
            return $v;
        }
        if (preg_match('#^[a-z0-9.\-]+\.[a-z]{2,}(/\S*)?$#i', $v)) {
            return 'https://'.$v;
        }

        return null;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Schnellsuche (Strg+K) über Seiten, Produkte, Bestellungen und Medien. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 80)).'%';
        $out = [];

        foreach (Page::where(fn ($w) => $w->where('title', 'like', $like)->orWhere('slug', 'like', $like))->orderBy('title')->limit(8)->get() as $p) {
            $out[] = ['label' => $p->title, 'hint' => $p->is_home ? '/' : '/'.$p->slug, 'url' => route('admin.pages.edit', $p), 'group' => 'Seiten'];
        }
        foreach (Product::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like))->orderBy('name')->limit(6)->get() as $p) {
            $out[] = ['label' => $p->name, 'hint' => $p->sku, 'url' => route('admin.shop.products.edit', $p), 'group' => 'Produkte'];
        }
        foreach (Order::where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', $like))->latest('id')->limit(6)->get() as $o) {
            $out[] = ['label' => 'Bestellung '.$o->number.' · '.$o->name, 'hint' => $o->created_at?->format('d.m.Y'), 'url' => route('admin.shop.orders.show', $o), 'group' => 'Bestellungen'];
        }
        foreach (Media::where(fn ($w) => $w->where('original_name', 'like', $like)->orWhere('title', 'like', $like)->orWhere('alt', 'like', $like))->latest('id')->limit(5)->get() as $m) {
            $out[] = ['label' => $m->title ?: $m->original_name, 'hint' => $m->alt ? mb_strimwidth($m->alt, 0, 40, '…') : $m->kind, 'url' => route('admin.media.index'), 'group' => 'Medien'];
        }

        return response()->json($out);
    }
}

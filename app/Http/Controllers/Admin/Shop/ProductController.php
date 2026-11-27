<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Shop\Money;
use App\Shop\ShopSettings;
use App\Support\Blocks\BlockSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('suche'));

        return view('admin.shop.products.index', [
            'products' => Product::query()
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
                ->orderBy('sort')->orderBy('name')->paginate(50)->withQueryString(),
            'search' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.shop.products.edit', ['product' => new Product(['is_active' => true, 'tax_rate' => ShopSettings::taxRates()[0], 'images' => []])]);
    }

    public function store(Request $request, BlockSanitizer $sanitizer): RedirectResponse
    {
        $product = Product::create($this->validated($request, $sanitizer));

        return redirect()->route('admin.shop.products.edit', $product)->with('status', 'Das Produkt wurde angelegt.');
    }

    public function edit(Product $product): View
    {
        return view('admin.shop.products.edit', ['product' => $product]);
    }

    public function update(Request $request, Product $product, BlockSanitizer $sanitizer): RedirectResponse
    {
        $product->update($this->validated($request, $sanitizer, $product));

        return back()->with('status', 'Gespeichert.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Produkte mit Bestellungen werden nur ausgeblendet, damit Rechnungen vollständig bleiben
        if (OrderItem::where('product_id', $product->id)->exists()) {
            $product->update(['is_active' => false]);

            return redirect()->route('admin.shop.products.index')->with('status', "„{$product->name}“ wurde bereits bestellt und ist jetzt ausgeblendet statt gelöscht.");
        }
        $product->delete();

        return redirect()->route('admin.shop.products.index')->with('status', "„{$product->name}“ wurde gelöscht.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, BlockSanitizer $sanitizer, ?Product $product = null): array
    {
        // Preise dürfen mit Komma eingegeben werden ("12,90")
        $request->merge([
            'price_cents' => Money::parse($request->input('price')),
            'compare_price_cents' => Money::parse($request->input('compare_price')),
            'base_quantity' => $request->filled('base_quantity') ? str_replace(',', '.', (string) $request->input('base_quantity')) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['nullable', 'string', 'max:60'],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:100000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'compare_price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'tax_rate' => ['required', 'numeric', Rule::in(ShopSettings::taxRates())],
            'stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'base_quantity' => ['nullable', 'numeric', 'min:0.001', 'max:100000'],
            'base_unit' => ['nullable', Rule::in(array_keys(ShopSettings::BASE_UNITS))],
            'delivery_time' => ['nullable', 'string', 'max:60'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['string', 'max:500'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'price_cents.required' => 'Bitte geben Sie einen Preis an, z. B. 12,90.',
            'price_cents.integer' => 'Der Preis ist keine gültige Zahl.',
            'slug.regex' => 'Die Adresse darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
            'slug.unique' => 'Diese Adresse hat schon ein anderes Produkt.',
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Product::uniqueSlug($data['name'], $product?->id);
        $data['description'] = ! empty($data['description']) ? $sanitizer->html($data['description']) : null;
        $data['images'] = array_values(array_filter(array_map(fn ($u) => $sanitizer->image($u), $data['images'] ?? [])));
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] = (int) ($data['sort'] ?? 0);
        if (empty($data['base_quantity']) || empty($data['base_unit'])) {
            $data['base_quantity'] = $data['base_unit'] = null;
        }

        return $data;
    }
}

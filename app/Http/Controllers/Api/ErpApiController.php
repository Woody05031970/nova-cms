<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Support\Erp\Erp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * REST-Schnittstelle für Warenwirtschaft und Buchhaltung (Modul Warenwirtschaft-Anbindung).
 * Anmeldung: Authorization: Bearer <Zugangsschlüssel>
 */
class ErpApiController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $products = Product::when($request->query('seit'), fn ($q, $v) => $q->where('updated_at', '>=', Carbon::parse($v)))
            ->orderBy('id')->limit(1000)->get();

        return response()->json(['data' => $products->map(fn ($p) => Erp::productJson($p))->values()]);
    }

    public function updateProduct(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return response()->json(['message' => 'Kein Produkt mit dieser Artikelnummer.'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'compare_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'stock' => ['sometimes', 'nullable', 'integer'],
            'active' => ['sometimes', 'boolean'],
            'delivery_time' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
        if (array_key_exists('active', $data)) {
            $data['is_active'] = $data['active'];
            unset($data['active']);
        }
        $product->update($data);

        return response()->json(['data' => Erp::productJson($product->fresh())]);
    }

    public function orders(Request $request): JsonResponse
    {
        $request->validate(['seit' => ['nullable', 'date']]);

        return response()->json(['data' => Erp::ordersSince($request->query('seit'))->map(fn ($o) => Erp::orderJson($o))->values()]);
    }

    public function updateOrder(Request $request, string $number): JsonResponse
    {
        $order = Order::where('number', $number)->first();
        if (! $order) {
            return response()->json(['message' => 'Keine Bestellung mit dieser Nummer.'], 404);
        }
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Order::STATUSES))],
            'payment_status' => ['sometimes', Rule::in(['paid'])],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        return response()->json(['data' => Erp::orderJson(Erp::updateOrder($order, $data))]);
    }
}

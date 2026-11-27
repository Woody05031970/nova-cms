<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Shop\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Modul „Gutscheine und Rabatte“: Rabattcodes und Geschenkgutscheine verwalten */
class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $gift = $request->query('art') === 'geschenk';

        return view('admin.coupons', [
            'coupons' => Coupon::where('is_gift', $gift)->latest()->limit(200)->get(),
            'gift' => $gift,
            'counts' => ['codes' => Coupon::where('is_gift', false)->count(), 'gifts' => Coupon::where('is_gift', true)->count()],
            'open' => (int) Coupon::where('is_gift', true)->where('is_active', true)->sum('value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = $data['code'] ?: Coupon::generateCode($data['is_gift'] ? 'GUT' : 'RABATT');
        $coupon = Coupon::create($data + ['used' => 0]);

        return redirect()->route('admin.coupons', $coupon->is_gift ? ['art' => 'geschenk'] : [])
            ->with('status', 'Code '.$coupon->code.' angelegt.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        if ($request->has('toggle')) {
            $coupon->update(['is_active' => ! $coupon->is_active]);

            return back()->with('status', $coupon->code.($coupon->is_active ? ' ist wieder aktiv.' : ' ist pausiert.'));
        }
        $coupon->update($this->validated($request, $coupon));

        return back()->with('status', $coupon->code.' gespeichert.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('status', 'Code gelöscht. Bestellungen, in denen er eingelöst wurde, bleiben unverändert.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => Coupon::normalize((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'min:3', 'max:40', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'kind' => ['required', Rule::in(array_keys(Coupon::KINDS))],
            'value' => ['nullable', 'string', 'max:20'],
            'min_order' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'code.unique' => 'Diesen Code gibt es schon.',
            'code.min' => 'Der Code braucht mindestens 3 Zeichen.',
            'ends_at.after_or_equal' => 'Das Ende muss nach dem Beginn liegen.',
        ]);

        $gift = $request->boolean('is_gift');
        $kind = $gift ? 'fixed' : $data['kind'];
        $value = match ($kind) {
            'percent' => (int) str_replace(',', '.', (string) ($data['value'] ?? '0')),
            'fixed' => (int) Money::parse($data['value'] ?? null),
            default => 0,
        };
        if ($kind === 'percent' && ($value < 1 || $value > 100)) {
            throw ValidationException::withMessages(['value' => 'Bitte einen Prozentwert zwischen 1 und 100 angeben.']);
        }
        if ($kind === 'fixed' && $value < 1) {
            throw ValidationException::withMessages(['value' => 'Bitte einen Betrag angeben, z. B. 10,00.']);
        }

        return [
            'code' => $data['code'] ?? '',
            'kind' => $kind,
            'value' => $value,
            'min_order_cents' => filled($data['min_order'] ?? null) ? Money::parse($data['min_order']) : null,
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at'])->startOfDay() : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'])->endOfDay() : null,
            'max_uses' => $gift ? null : ($data['max_uses'] ?? null),
            'is_gift' => $gift,
            'note' => $data['note'] ?? null,
            'is_active' => $coupon?->is_active ?? true,
        ];
    }
}

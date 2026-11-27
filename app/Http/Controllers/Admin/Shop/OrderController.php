<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Models\Setting;
use App\Plugins\Hooks;
use App\Shop\Checkout;
use App\Shop\Payments\PaymentRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function show(Order $order, PaymentRegistry $payments): View
    {
        return view('admin.shop.orders.show', [
            'order' => $order->load('items.product'),
            'payment' => $payments->get($order->payment_method),
        ]);
    }

    public function update(Request $request, Order $order, Hooks $hooks): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['paid', 'shipped', 'completed', 'cancelled', 'processing'])],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'notify' => ['boolean'],
        ]);
        if ($order->status === 'cancelled') {
            return back()->withErrors(['action' => 'Diese Bestellung ist storniert und kann nicht mehr geändert werden.']);
        }

        $message = match ($data['action']) {
            'paid' => tap('Zahlung als eingegangen markiert.', fn () => $order->markPaid()),
            'processing' => tap('Status: In Bearbeitung.', fn () => $order->update(['status' => 'processing'])),
            'shipped' => tap('Als versendet markiert.', function () use ($order, $data, $request) {
                $order->update(['status' => 'shipped', 'shipped_at' => now(), 'tracking_number' => $data['tracking_number'] ?? $order->tracking_number]);
                if ($request->boolean('notify')) {
                    try {
                        Mail::to($order->email)->send(new OrderShipped($order->load('items')));
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            }),
            'completed' => tap('Bestellung abgeschlossen.', fn () => $order->update(['status' => 'completed'])),
            'cancelled' => tap('Bestellung storniert, der Lagerbestand wurde zurückgebucht.', function () use ($order) {
                Checkout::restock($order->load('items'));
                $order->update(['status' => 'cancelled']);
            }),
        };
        $hooks->doAction('shop.order.updated', $order->fresh(), $data['action']);

        return back()->with('status', $message);
    }

    public function invoice(Order $order, PaymentRegistry $payments): View
    {
        return view('admin.shop.orders.invoice', [
            'order' => $order->load('items'),
            'payment' => $payments->get($order->payment_method),
            's' => fn ($k) => Setting::get($k),
        ]);
    }
}

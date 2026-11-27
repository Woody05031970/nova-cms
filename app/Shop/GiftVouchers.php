<?php

namespace App\Shop;

use App\Licensing\Features;
use App\Mail\VoucherMail;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Modul „Gutscheine und Rabatte“: verkaufte Geschenkgutscheine nach der Zahlung ausstellen */
class GiftVouchers
{
    /** @return list<Coupon> */
    public function issue(Order $order): array
    {
        if (! Features::on('gutscheine') || Coupon::where('order_id', $order->id)->exists()) {
            return [];
        }
        $order->loadMissing('items.product');
        $codes = [];
        foreach ($order->items as $item) {
            if (! $item->product?->is_voucher) {
                continue;
            }
            for ($i = 0; $i < $item->quantity; $i++) {
                $codes[] = Coupon::create([
                    'code' => Coupon::generateCode(), 'kind' => 'fixed', 'value' => $item->price_cents, 'is_gift' => true,
                    'order_id' => $order->id, 'ends_at' => now()->addYears(3)->endOfYear(), 'note' => 'Bestellung '.$order->number,
                ]);
            }
        }
        if ($codes) {
            try {
                Mail::to($order->email)->send(new VoucherMail($order, $codes));
            } catch (Throwable $e) {
                report($e); // Codes stehen auch in der Verwaltung unter „Gutscheine“
            }
        }

        return $codes;
    }
}

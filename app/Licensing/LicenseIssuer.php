<?php

namespace App\Licensing;

use App\Mail\LicenseIssued;
use App\Models\IssuedLicense;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auf dem Lizenzserver: erzeugt nach Zahlungseingang die Lizenzen für gekaufte Produkte
 * und schickt die Schlüssel per E-Mail.
 */
class LicenseIssuer
{
    /** @return list<IssuedLicense> */
    public function forOrder(Order $order): array
    {
        if (IssuedLicense::where('order_id', $order->id)->exists()) {
            return []; // schon ausgegeben
        }

        $issued = [];
        foreach ($order->items()->with('product')->get() as $item) {
            $spec = $item->product?->license_spec;
            if (! $spec || (empty($spec['core']) && empty($spec['modules']))) {
                continue;
            }
            $issued[] = IssuedLicense::issue([
                'customer_name' => $order->company ?: $order->name,
                'customer_email' => $order->email,
                'core' => (bool) ($spec['core'] ?? false),
                'modules' => array_values($spec['modules'] ?? []),
                'max_domains' => max(1, (int) ($spec['max_domains'] ?? 1)) * $item->quantity,
                'updates_until' => ! empty($spec['update_months']) ? now()->addMonths((int) $spec['update_months'])->toDateString() : null,
                'order_id' => $order->id,
                'notes' => 'Aus Bestellung '.$order->number.': '.$item->name,
            ]);
        }

        if ($issued) {
            try {
                Mail::to($order->email)->send(new LicenseIssued($order, $issued));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $issued;
    }
}

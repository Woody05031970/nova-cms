<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        <h1 style="font-size:20px;margin:0 0 12px">Vielen Dank für Ihren Einkauf!</h1>
        <p style="margin:0 0 16px">Hallo {{ $order->name }}, hier {{ count($coupons) === 1 ? 'ist Ihr Gutschein' : 'sind Ihre Gutscheine' }}. Einfach im Warenkorb unter „Gutschein einlösen“ eingeben. Ein Restwert bleibt für den nächsten Einkauf erhalten.</p>
        @foreach ($coupons as $c)
            <div style="border:2px dashed #6366f1;border-radius:12px;padding:16px;margin:0 0 12px;text-align:center">
                <p style="margin:0;font-size:13px;color:#475569">Gutschein über</p>
                <p style="margin:4px 0;font-size:24px;font-weight:bold">{{ \App\Shop\Money::format($c->value) }}</p>
                <p style="margin:8px 0 0;font-family:monospace;font-size:20px;letter-spacing:2px">{{ $c->code }}</p>
                <p style="margin:8px 0 0;font-size:12px;color:#475569">Gültig bis {{ $c->ends_at->format('d.m.Y') }} bei {{ $site }}</p>
            </div>
        @endforeach
        <p style="margin:16px 0 0;font-size:13px;color:#475569">{{ $site }} · {{ url('/') }}</p>
    </div>
</div>
</body></html>

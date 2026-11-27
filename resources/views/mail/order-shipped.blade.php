<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        <h1 style="font-size:20px;margin:0 0 8px">Ihre Bestellung ist unterwegs</h1>
        <p>Hallo {{ $order->name }},<br>Ihre Bestellung <strong>{{ $order->number }}</strong> wurde heute versendet.</p>
        @if ($order->tracking_number)<p>Sendungsnummer: <strong>{{ $order->tracking_number }}</strong></p>@endif
        @include('mail.partials.items')
        <p style="margin-top:20px">Viel Freude damit!<br>Ihr Team von {{ $shop }}</p>
    </div>
</div>
</body></html>

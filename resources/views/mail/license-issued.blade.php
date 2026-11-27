<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        <h1 style="font-size:20px;margin:0 0 8px">Ihr Lizenzschlüssel</h1>
        <p>Hallo {{ $order->name }},<br>vielen Dank für Ihren Kauf. Hier {{ count($licenses) === 1 ? 'ist Ihr Lizenzschlüssel' : 'sind Ihre Lizenzschlüssel' }}:</p>
        @foreach ($licenses as $l)
            <p style="font-family:Consolas,monospace;font-size:20px;letter-spacing:1px;background:#f1f5f9;border-radius:8px;padding:12px 16px;text-align:center">{{ $l->key }}</p>
            <p style="font-size:13px;color:#475569">{{ $l->notes }} · gültig für {{ $l->max_domains }} {{ $l->max_domains === 1 ? 'Website' : 'Websites' }}@if ($l->updates_until), Updates bis {{ $l->updates_until->format('d.m.Y') }}@endif</p>
        @endforeach
        <p><strong>So geht es weiter:</strong> Melden Sie sich in der Verwaltung Ihrer Website an, öffnen Sie unten links „Lizenz“ und geben Sie den Schlüssel ein. Gekaufte Module finden Sie danach unter Plugins › Marktplatz.</p>
        <p>Ihr Team von {{ $shop }}</p>
    </div>
</div>
</body></html>
